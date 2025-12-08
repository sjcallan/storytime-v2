<?php

namespace App\Services\Ai\OpenAi;

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

    protected string $model = 'gpt-4.1';

    protected ?string $id = null;

    protected string $completion = '';

    /** @var float Cost per 1000 tokens for GPT-4.1 */
    protected const MODEL_COST_PER_1K_TOKENS = 0.002;

    protected RequestLogService $requestLogService;

    public function __construct(AiApiServiceInterface $apiService, RequestLogService $requestLogService)
    {
        $this->apiService = $apiService;
        $this->requestLogService = $requestLogService;
        $this->apiService->setModel('gpt-4.1');
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
                'model' => 0,
                'cost_per_token' => 0,
                'id' => $this->getId(),
                'error' => json_encode($this->apiService->getError()),
            ];
        }

        $this->response = $response;
        $this->model = $response['model'];
        $this->completion = $response['choices'][0]['message']['content'];
        $this->promptTokens = $response['usage']['prompt_tokens'];

        if (array_key_exists('completion_tokens', $response['usage'])) {
            $this->completionTokens = $response['usage']['completion_tokens'];
        }

        $this->totalTokens = $response['usage']['total_tokens'];
        $this->id = $response['id'];

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
                'model' => 0,
                'cost_per_token' => 0,
                'id' => $this->getId(),
                'error' => json_encode($response['error'] ?? $this->apiService->getError()),
            ];
        }

        $this->response = $response;
        $this->completion = $response['choices'][0]['text'];
        $this->promptTokens = $response['usage']['prompt_tokens'];
        $this->completionTokens = $response['usage']['completion_tokens'];
        $this->totalTokens = $response['usage']['total_tokens'];
        $this->id = $response['id'];

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

    public function trackRequestLog(string $bookId, string $chapterId, string $userId, string $itemType, array $response): void
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
            $this->apiService->getResponseTime()
        ));

    }

    public function getTotalCost(): float
    {
        $totalCost = round($this->getCostPerToken() * ($this->getTotalTokens() / 1000), 8);

        return $totalCost;
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
     * Set the model. Allows gpt-4.1, gpt-4.1, gpt-5.1, gpt-4o-mini, and gpt-4.1-mini.
     */
    public function setModel(string $model): void
    {
        $this->apiService->setModel($model);
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->apiService->setMaxTokens($maxTokens);
    }

    public function getError(): ?string
    {
        return $this->apiService->getError();
    }
}
