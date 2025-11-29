<?php

namespace App\Services\OpenAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $maxTokens = 4000;

    protected string $model = 'gpt-4.1';

    protected float $temperature = 0.8;

    protected ?string $error = null;

    protected ?array $response = null;

    protected ?array $request = null;

    protected ?float $responseStartTime = null;

    protected ?float $responseEndTime = null;

    protected ?int $responseStatusCode = null;

    protected string $responseFormat = 'text';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * @param  string  $format  - text or json_object
     */
    public function setResponseFormat(string $format): void
    {
        $this->responseFormat = $format;
    }

    public function completion(string $prompt): ?array
    {
        $this->setStartTime();

        $settings = [
            'model' => $this->model,
            'prompt' => $prompt,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];
        $this->request = $settings;

        $url = "{$this->baseUrl}/completions";

        Log::info('OpenAI Completion Request', [
            'url' => $url,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $httpResponse = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('OpenAI Completion Exception', [
                'exception' => $e->getMessage(),
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = 'Request failed: '.$e->getMessage();
            $this->responseStatusCode = 500;

            return null;
        }

        $this->setEndTime();

        if ($httpResponse->failed()) {
            Log::error('OpenAI Completion Failed', [
                'status' => $httpResponse->status(),
                'body' => $httpResponse->body(),
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $httpResponse->body() ?: 'No response';
            $this->responseStatusCode = $httpResponse->status();

            return null;
        }

        $response = $httpResponse->json();

        if (isset($response['error'])) {
            Log::error('OpenAI Completion Error Response', [
                'error' => $response['error'],
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $response['error']['message'] ?? 'Unknown error';
            $this->responseStatusCode = 500;

            return null;
        }

        $this->response = $response;
        $this->responseStatusCode = $httpResponse->status();

        Log::info('OpenAI Completion Success', [
            'model' => $this->model,
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
        ]);

        return $response;
    }

    public function image(string $prompt): ?array
    {
        $this->setStartTime();

        $settings = [
            'size' => '512x512',
            'prompt' => $prompt,
        ];
        $this->request = $settings;

        $url = "{$this->baseUrl}/images/generations";

        Log::info('OpenAI Image Request', [
            'url' => $url,
            'size' => '512x512',
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $httpResponse = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('OpenAI Image Exception', [
                'exception' => $e->getMessage(),
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = 'Request failed: '.$e->getMessage();
            $this->responseStatusCode = 500;

            return null;
        }

        $this->setEndTime();

        if ($httpResponse->failed()) {
            Log::error('OpenAI Image Failed', [
                'status' => $httpResponse->status(),
                'body' => $httpResponse->body(),
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $httpResponse->body() ?: 'No response';
            $this->responseStatusCode = $httpResponse->status();

            return null;
        }

        $this->response = $httpResponse->json();

        if (isset($this->response['error'])) {
            Log::error('OpenAI Image Error Response', [
                'error' => $this->response['error'],
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $this->response['error']['message'] ?? 'Unknown error';
            $this->responseStatusCode = 500;

            return null;
        }

        $this->responseStatusCode = $httpResponse->status();

        Log::info('OpenAI Image Success', [
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
        ]);

        return $this->response;
    }

    public function chat(array $messages): ?array
    {
        $this->setStartTime();

        $settings = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
        ];

        if ($this->responseFormat !== 'text') {
            $settings['response_format'] = ['type' => $this->responseFormat];
        }

        $this->request = $settings;

        $url = "{$this->baseUrl}/chat/completions";

        Log::info('OpenAI Chat Request', [
            'url' => $url,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'response_format' => $this->responseFormat,
            'message_count' => count($messages),
            'has_api_key' => ! empty($this->apiKey),
        ]);

        Log::debug('OpenAI Chat Request Full', [
            'settings' => $settings,
        ]);

        try {
            $httpResponse = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('OpenAI Chat Exception', [
                'exception' => $e->getMessage(),
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = 'Request failed: '.$e->getMessage();
            $this->responseStatusCode = 500;

            return null;
        }

        $this->setEndTime();

        if ($httpResponse->failed()) {
            Log::error('OpenAI Chat Failed', [
                'status' => $httpResponse->status(),
                'body' => $httpResponse->body(),
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->responseStatusCode = $httpResponse->status();
            $this->error = $httpResponse->body() ?: 'No response';

            return null;
        }

        $this->response = $httpResponse->json();
        $this->responseStatusCode = $httpResponse->status();

        if (isset($this->response['error'])) {
            Log::error('OpenAI Chat Error Response', [
                'error' => $this->response['error'],
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $this->response['error']['message'] ?? 'Unknown error';
            $this->responseStatusCode = 500;

            return null;
        }

        Log::info('OpenAI Chat Success', [
            'model' => $this->response['model'] ?? $this->model,
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
            'prompt_tokens' => $this->response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $this->response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $this->response['usage']['total_tokens'] ?? 0,
        ]);

        Log::debug('OpenAI Chat Response Full', [
            'response' => $this->response,
        ]);

        return $this->response;
    }

    /**
     * @param  float  $temperature  0.01 - 1
     */
    public function setTemperature(float $temperature): float
    {
        return $this->temperature = $temperature;
    }

    public function getResponseStatusCode(): ?int
    {
        return $this->responseStatusCode;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }

    public function getRequest(): ?array
    {
        return $this->request;
    }

    protected function setStartTime(): void
    {
        $this->responseStartTime = microtime(true);
    }

    protected function setEndTime(): void
    {
        $this->responseEndTime = microtime(true);
    }

    public function getResponseTime(): float
    {
        if ($this->responseStartTime === null || $this->responseEndTime === null) {
            return 0.0;
        }

        return $this->responseEndTime - $this->responseStartTime;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Model is locked to GPT-4.1
     */
    /**
     * Set the model. Allows gpt-4.1, gpt-5.1, gpt-4o-mini, and gpt-4.1-mini.
     */
    public function setModel(string $model): void
    {
        $allowedModels = ['gpt-4.1', 'gpt-5.1', 'gpt-4o-mini', 'gpt-4.1-mini'];

        if (in_array($model, $allowedModels)) {
            $this->model = $model;
        } else {
            $this->model = 'gpt-4.1';
        }
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }
}
