<?php

namespace App\Services\Ai\Nemotron3;

use App\Jobs\TrackRequestJob;
use App\Services\Ai\Contracts\AiApiServiceInterface;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use App\Services\RequestLog\RequestLogService;

class ChatService implements AiChatServiceInterface
{
    protected AiApiServiceInterface $apiService;

    /** @var array<int, array{role: string, content: string}> */
    protected array $messages = [];

    /** @var array<string, mixed> */
    protected array $response = [];

    protected int $promptTokens = 0;

    protected int $completionTokens = 0;

    protected int $totalTokens = 0;

    protected string $model = 'Nemotron-3-Nano';

    protected ?string $id = null;

    protected string $completion = '';

    protected ?string $thinking = null;

    /** @var float Cost per 1000 tokens - local model has no cost */
    protected const MODEL_COST_PER_1K_TOKENS = 0.0;

    protected RequestLogService $requestLogService;

    public function __construct(AiApiServiceInterface $apiService, RequestLogService $requestLogService)
    {
        $this->apiService = $apiService;
        $this->requestLogService = $requestLogService;
        $this->model = config('ai.providers.nemotron3.model', 'Nemotron-3-Nano');
    }

    public function setResponseFormat(string $responseFormat = 'text'): void
    {
        $this->apiService->setResponseFormat($responseFormat);
    }

    public function chat(): array
    {
        $response = $this->apiService->chat($this->messages);

        if (! $response || array_key_exists('error', $response)) {
            return [
                'response' => '',
                'completion' => '',
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'model' => $this->model,
                'cost_per_token' => 0,
                'id' => $this->getId(),
                'error' => json_encode($this->apiService->getError()),
            ];
        }

        $this->response = $response;
        $this->model = $response['model'] ?? $this->model;

        $rawContent = $response['choices'][0]['message']['content'] ?? '';
        $parsed = $this->extractThinking($rawContent);
        $this->thinking = $parsed['thinking'];
        $this->completion = $parsed['content'];

        $this->promptTokens = $response['usage']['prompt_tokens'] ?? 0;

        if (array_key_exists('completion_tokens', $response['usage'] ?? [])) {
            $this->completionTokens = $response['usage']['completion_tokens'];
        }

        $this->totalTokens = $response['usage']['total_tokens'] ?? 0;
        $this->id = $response['id'] ?? null;

        return [
            'response' => $this->response,
            'completion' => $this->getCompletion(),
            'thinking' => $this->getThinking(),
            'prompt_tokens' => $this->getPromptTokens(),
            'completion_tokens' => $this->getCompletionTokens(),
            'total_tokens' => $this->getTotalTokens(),
            'total_cost' => $this->getTotalCost(),
            'model' => $this->getModel(),
            'cost_per_token' => $this->getCostPerToken(),
            'id' => $this->getId(),
        ];
    }

    public function complete(string $prompt): array
    {
        $response = $this->apiService->completion($prompt);

        if (! $response || array_key_exists('error', $response)) {
            return [
                'response' => $response ?? [],
                'completion' => '',
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'model' => $this->model,
                'cost_per_token' => 0,
                'id' => $this->getId(),
                'error' => json_encode($response['error'] ?? $this->apiService->getError()),
            ];
        }

        $this->response = $response;
        $this->completion = $response['choices'][0]['text'] ?? '';
        $this->promptTokens = $response['usage']['prompt_tokens'] ?? 0;
        $this->completionTokens = $response['usage']['completion_tokens'] ?? 0;
        $this->totalTokens = $response['usage']['total_tokens'] ?? 0;
        $this->id = $response['id'] ?? null;

        return [
            'response' => $this->response,
            'completion' => $this->getCompletion(),
            'prompt_tokens' => $this->getPromptTokens(),
            'completion_tokens' => $this->getCompletionTokens(),
            'total_tokens' => $this->getTotalTokens(),
            'total_cost' => $this->getTotalCost(),
            'model' => $this->getModel(),
            'cost_per_token' => $this->getCostPerToken(),
            'id' => $this->getId(),
        ];
    }

    public function trackRequestLog(string $bookId, string $chapterId, string $userId, string $itemType, array $response, ?string $profileId = null): void
    {
        dispatch(new TrackRequestJob(
            $bookId,
            $chapterId,
            $userId,
            $itemType,
            json_encode($this->apiService->getRequest()),
            json_encode($response),
            json_encode($this->apiService->getResponse()),
            $this->apiService->getResponseStatusCode(),
            $this->apiService->getResponseTime(),
            $profileId
        ));
    }

    public function getTotalCost(): float
    {
        return round($this->getCostPerToken() * ($this->getTotalTokens() / 1000), 8);
    }

    public function getCostPerToken(): float
    {
        return self::MODEL_COST_PER_1K_TOKENS;
    }

    public function addAssistantMessage(?string $message = null): void
    {
        if (! $message) {
            return;
        }

        array_push($this->messages, [
            'role' => 'assistant',
            'content' => $message.' ',
        ]);
    }

    public function addSystemMessage(?string $message = null): void
    {
        if (! $message) {
            return;
        }

        array_push($this->messages, [
            'role' => 'system',
            'content' => $message.' ',
        ]);
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function setTemperature(float $temperature): float
    {
        return $this->apiService->setTemperature($temperature);
    }

    public function getCompletion(): string
    {
        return $this->completion;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCompletionTokens(): string
    {
        return (string) $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function resetMessages(): void
    {
        $this->messages = [];
    }

    public function addUserMessage(?string $message = null): void
    {
        if (! $message) {
            return;
        }

        array_push($this->messages, [
            'role' => 'user',
            'content' => $message.' ',
        ]);
    }

    public function setContext(?string $message = null): void
    {
        if (! $message) {
            return;
        }

        array_push($this->messages, [
            'role' => 'system',
            'content' => $message.' ',
        ]);
    }

    /**
     * Set the model for Nemotron3.
     */
    public function setModel(string $model): void
    {
        $this->apiService->setModel($model);
        $this->model = $model;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->apiService->setMaxTokens($maxTokens);
    }

    public function getError(): ?string
    {
        return $this->apiService->getError();
    }

    public function getThinking(): ?string
    {
        return $this->thinking;
    }

    /**
     * Extract thinking content from response that ends with </think> tag.
     * Everything before </think> is thinking, everything after is the actual content.
     *
     * @return array{thinking: string|null, content: string}
     */
    protected function extractThinking(string $rawContent): array
    {
        $thinking = null;
        $content = $rawContent;

        if (str_contains($rawContent, '</think>')) {
            $parts = explode('</think>', $rawContent, 2);
            $thinking = trim($parts[0]);
            $content = trim($parts[1] ?? '');
        }

        return [
            'thinking' => $thinking,
            'content' => $content,
        ];
    }
}
