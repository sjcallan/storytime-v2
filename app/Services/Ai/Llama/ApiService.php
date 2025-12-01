<?php

namespace App\Services\Ai\Llama;

use App\Services\Ai\Contracts\AiApiServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiService implements AiApiServiceInterface
{
    protected string $baseUrl;

    protected string $endpoint;

    protected int $maxTokens = 4000;

    protected string $model = 'llama-3.2';

    protected float $temperature = 0.8;

    protected ?string $error = null;

    protected ?array $response = null;

    protected ?array $request = null;

    protected ?float $responseStartTime = null;

    protected ?float $responseEndTime = null;

    protected ?int $responseStatusCode = null;

    protected string $responseFormat = 'text';

    protected int $timeout = 300;

    public function __construct()
    {
        $this->baseUrl = config('ai.providers.llama.base_url', 'http://127.0.0.1:5009');
        $this->endpoint = config('ai.providers.llama.endpoint', '/generate');
        $this->model = config('ai.providers.llama.model', 'llama-3.2');
        $this->maxTokens = config('ai.providers.llama.max_tokens', 4000);
        $this->temperature = config('ai.providers.llama.temperature', 0.8);
        $this->timeout = config('ai.providers.llama.timeout', 300);
    }

    public function setResponseFormat(string $format): void
    {
        $this->responseFormat = $format;
    }

    public function completion(string $prompt): ?array
    {
        return $this->sendRequest($prompt, 'completion');
    }

    public function chat(array $messages): ?array
    {
        $prompt = $this->convertMessagesToPrompt($messages);

        return $this->sendRequest($prompt, 'chat');
    }

    /**
     * Convert chat messages to a prompt string for Llama.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function convertMessagesToPrompt(array $messages): string
    {
        $prompt = '';

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';

            switch ($role) {
                case 'system':
                    $prompt .= "<|system|>\n{$content}\n";
                    break;
                case 'assistant':
                    $prompt .= "<|assistant|>\n{$content}\n";
                    break;
                case 'user':
                default:
                    $prompt .= "<|user|>\n{$content}\n";
                    break;
            }
        }

        $prompt .= "<|assistant|>\n";

        return $prompt;
    }

    /**
     * Send request to the Llama API.
     */
    protected function sendRequest(string $prompt, string $type = 'chat'): ?array
    {
        $this->setStartTime();
        $this->error = null;

        $settings = [
            'prompt' => $prompt,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'model' => $this->model,
        ];

        if ($this->responseFormat === 'json_object') {
            $settings['response_format'] = ['type' => 'json_object'];
        }

        $this->request = $settings;
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($this->endpoint, '/');

        Log::info('Llama Request', [
            'url' => $url,
            'type' => $type,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'response_format' => $this->responseFormat,
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $httpResponse = Http::timeout($this->timeout)
                ->post($url, $settings);
        } catch (\Exception $e) {
            $this->setEndTime();
            Log::error('Llama Request Exception', [
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
            Log::error('Llama Request Failed', [
                'status' => $httpResponse->status(),
                'body' => $httpResponse->body(),
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $httpResponse->body() ?: 'No response';
            $this->responseStatusCode = $httpResponse->status();

            return null;
        }

        $rawResponse = $httpResponse->json();

        if (isset($rawResponse['error'])) {
            Log::error('Llama Error Response', [
                'error' => $rawResponse['error'],
                'model' => $this->model,
                'response_time' => $this->getResponseTime(),
            ]);
            $this->error = $rawResponse['error']['message'] ?? $rawResponse['error'] ?? 'Unknown error';
            $this->responseStatusCode = 500;

            return null;
        }

        $this->responseStatusCode = $httpResponse->status();
        $this->response = $this->normalizeResponse($rawResponse, $type, $prompt);

        Log::info('Llama Success', [
            'model' => $this->response['model'] ?? $this->model,
            'status' => $this->responseStatusCode,
            'response_time' => round($this->getResponseTime(), 2).'s',
            'prompt_tokens' => $this->response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $this->response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $this->response['usage']['total_tokens'] ?? 0,
        ]);

        return $this->response;
    }

    /**
     * Normalize Llama response to match OpenAI format.
     *
     * @param  array<string, mixed>  $rawResponse
     * @return array<string, mixed>
     */
    protected function normalizeResponse(array $rawResponse, string $type, string $prompt): array
    {
        $content = $rawResponse['response'] ?? $rawResponse['generated_text'] ?? $rawResponse['text'] ?? '';

        $promptTokens = $rawResponse['prompt_tokens'] ?? $this->estimateTokens($prompt);
        $completionTokens = $rawResponse['completion_tokens'] ?? $this->estimateTokens($content);
        $totalTokens = $rawResponse['total_tokens'] ?? ($promptTokens + $completionTokens);

        if ($type === 'chat') {
            return [
                'id' => $rawResponse['id'] ?? 'llama-'.Str::uuid(),
                'object' => 'chat.completion',
                'created' => $rawResponse['created'] ?? time(),
                'model' => $rawResponse['model'] ?? $this->model,
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $content,
                        ],
                        'finish_reason' => $rawResponse['finish_reason'] ?? 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                ],
            ];
        }

        return [
            'id' => $rawResponse['id'] ?? 'llama-'.Str::uuid(),
            'object' => 'text_completion',
            'created' => $rawResponse['created'] ?? time(),
            'model' => $rawResponse['model'] ?? $this->model,
            'choices' => [
                [
                    'text' => $content,
                    'index' => 0,
                    'finish_reason' => $rawResponse['finish_reason'] ?? 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
            ],
        ];
    }

    /**
     * Estimate token count (rough approximation).
     */
    protected function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
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
        return 'llama';
    }
}
