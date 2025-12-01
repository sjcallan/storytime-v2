<?php

namespace App\Services\OpenAi;

use App\Jobs\TrackRequestJob;
use App\Services\RequestLog\RequestLogService;

class ChatService
{
    /** @var \App\Services\OpenAi\ApiService */
    protected $openAiService;

    /** @var array */
    protected $messages = [];

    /** @var array */
    protected $response = 0;

    /** @var int */
    protected $promptTokens = 0;

    /** @var int */
    protected $completionTokens = 0;

    /** @var int */
    protected $totalTokens = 0;

    /** @var string */
    protected $model = 'gpt-4.1';

    /** @var string */
    protected $id;

    /** @var string */
    protected $completion;

    /** @var float Cost per 1000 tokens for GPT-4.1 */
    protected const MODEL_COST_PER_1K_TOKENS = 0.002;

    /** @var \App\Services\RequestLog\RequestLogService */
    protected $requestLogService;

    public function __construct(ApiService $openAiService, RequestLogService $requestLogService)
    {
        $this->openAiService = $openAiService;
        $this->requestLogService = $requestLogService;
        $this->openAiService->setModel('gpt-4.1');
    }

    /**
     * @param  string  $responseFormat  - text or json_object
     */
    public function setResponseFormat(string $responseFormat = 'text'): void
    {
        $this->openAiService->setResponseFormat($responseFormat);
    }

    public function chat(): array
    {
        $this->response = $this->openAiService->chat($this->messages);

        if (! $this->response || array_key_exists('error', $this->response)) {
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
                'error' => json_encode($this->openAiService->getError()),
            ];
        }

        $this->model = $this->response['model'];
        $this->completion = $this->response['choices'][0]['message']['content'];
        $this->promptTokens = $this->response['usage']['prompt_tokens'];

        if (array_key_exists('completion_tokens', $this->response['usage'])) {
            $this->completionTokens = $this->response['usage']['completion_tokens'];
        }

        $this->totalTokens = $this->response['usage']['total_tokens'];
        $this->id = $this->response['id'];

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
        $this->response = $this->openAiService->completion($prompt);

        if (array_key_exists('error', $this->response)) {
            return [
                'response' => $this->response,
                'completion' => '',
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'model' => 0,
                'cost_per_token' => 0,
                'id' => $this->getId(),
                'error' => json_encode($this->response['error']),
            ];
        }

        $this->completion = $this->response['choices'][0]['text'];
        $this->promptTokens = $this->response['usage']['prompt_tokens'];
        $this->completionTokens = $this->response['usage']['completion_tokens'];
        $this->totalTokens = $this->response['usage']['total_tokens'];
        $this->id = $this->response['id'];

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

    public function trackRequestLog(string $bookId, string $chapterId, string $userId, string $itemType, array $response)
    {
        dispatch(new TrackRequestJob(
            $bookId,
            $chapterId,
            $userId,
            $itemType,
            json_encode($this->openAiService->getRequest()),
            json_encode($response),
            json_encode($this->openAiService->getResponse()),
            $this->openAiService->getResponseStatusCode(),
            $this->openAiService->getResponseTime()
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

    /**
     * @params tring $message
     */
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

    /**
     * @params tring $message
     */
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
        return $this->openAiService->setTemperature($temperature);
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
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function resetMessages(): void
    {
        $this->messages = [];
    }

    /**
     * @params tring $message
     */
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

    /**
     * @params tring $message
     */
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
     * Set the model. Allows gpt-4.1, gpt-5.1, gpt-4o-mini, and gpt-4.1-mini.
     */
    public function setModel(string $model): void
    {
        $this->openAiService->setModel($model);
    }

    public function setMaxTokens(int $maxTokens)
    {
        $this->openAiService->setMaxTokens($maxTokens);
    }

    public function getError()
    {
        return $this->openAiService->getError();
    }
}
