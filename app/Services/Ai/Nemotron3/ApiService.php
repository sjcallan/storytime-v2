<?php

namespace App\Services\Ai\Nemotron3;

use App\Services\Ai\Contracts\AiApiServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService implements AiApiServiceInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $maxTokens = 16384;

    protected string $model = 'Nemotron-3-Nano';

    protected float $temperature = 0.8;

    protected ?string $error = null;

    protected ?array $response = null;

    protected ?array $request = null;

    protected ?float $responseStartTime = null;

    protected ?float $responseEndTime = null;

    protected ?int $responseStatusCode = null;

    protected string $responseFormat = 'text';

    protected int $timeout = 360;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.nemotron3.api_key', 'sk-no-key-required');
        $this->baseUrl = config('ai.providers.nemotron3.base_url', 'http://127.0.0.1:8001/v1');
        $this->model = config('ai.providers.nemotron3.model', 'unsloth/Nemotron-3-Nano-30B-A3B');
        $this->maxTokens = config('ai.providers.nemotron3.max_tokens', 4000);
        $this->temperature = config('ai.providers.nemotron3.temperature', 0.8);
        $this->timeout = config('ai.providers.nemotron3.timeout', 360);
    }

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

        Log::info('Nemotron3 Completion Request', [
            'url' => $url,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $httpResponse = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('Nemotron3 Completion Exception', [
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
            Log::error('Nemotron3 Completion Failed', [
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
            Log::error('Nemotron3 Completion Error Response', [
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

        Log::info('Nemotron3 Completion Success', [
            'model' => $this->model,
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
        ]);

        return $response;
    }

    /**
     * Image generation is not supported by Nemotron3 local server.
     *
     * @return array<string, mixed>|null
     */
    public function image(string $prompt): ?array
    {
        $this->error = 'Image generation is not supported by Nemotron3';
        $this->responseStatusCode = 501;

        Log::warning('Nemotron3 Image Generation Not Supported', [
            'prompt_length' => strlen($prompt),
        ]);

        return null;
    }

    public function chat(array $messages): ?array
    {
        $this->setStartTime();

        $settings = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        if ($this->responseFormat !== 'text') {
            $settings['response_format'] = ['type' => $this->responseFormat];
        }

        $this->request = $settings;

        $url = "{$this->baseUrl}/chat/completions";

        Log::info('Nemotron3 Chat Request', [
            'url' => $url,
            'model' => $this->model,
            'response_format' => $this->responseFormat,
            'message_count' => count($messages),
        ]);

        Log::debug('Nemotron3 Chat Request Full', [
            'settings' => $settings,
        ]);

        try {
            $httpResponse = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('Nemotron3 Chat Exception', [
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
            Log::error('Nemotron3 Chat Failed', [
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
            Log::error('Nemotron3 Chat Error Response', [
                'error' => $this->response['error'],
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $this->response['error']['message'] ?? 'Unknown error';
            $this->responseStatusCode = 500;

            return null;
        }

        Log::info('Nemotron3 Chat Success', [
            'model' => $this->response['model'] ?? $this->model,
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
            'prompt_tokens' => $this->response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $this->response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $this->response['usage']['total_tokens'] ?? 0,
        ]);

        Log::debug('Nemotron3 Chat Response Full', [
            'response' => $this->response,
        ]);

        return $this->response;
    }

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
     * Set the model for Nemotron3.
     */
    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }

    public function getProviderName(): string
    {
        return 'nemotron3';
    }
}
