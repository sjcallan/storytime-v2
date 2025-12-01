<?php

namespace App\Services\Ai\Contracts;

interface AiChatServiceInterface
{
    /**
     * Execute a chat completion and return the formatted response.
     *
     * @return array{
     *     response: array<string, mixed>|string,
     *     completion: string,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     total_cost: float,
     *     model: string,
     *     cost_per_token: float,
     *     id: string|null,
     *     error?: string
     * }
     */
    public function chat(): array;

    /**
     * Execute a text completion.
     *
     * @return array{
     *     response: array<string, mixed>|string,
     *     completion: string,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     total_cost: float,
     *     model: string,
     *     cost_per_token: float,
     *     id: string|null,
     *     error?: string
     * }
     */
    public function complete(string $prompt): array;

    /**
     * Set the response format.
     *
     * @param  string  $responseFormat  - text or json_object
     */
    public function setResponseFormat(string $responseFormat): void;

    /**
     * Add an assistant message to the conversation.
     */
    public function addAssistantMessage(?string $message): void;

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(?string $message): void;

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(?string $message): void;

    /**
     * Set the context (system message).
     */
    public function setContext(?string $message): void;

    /**
     * Reset all messages in the conversation.
     */
    public function resetMessages(): void;

    /**
     * Set the model to use.
     */
    public function setModel(string $model): void;

    /**
     * Set the temperature for generation.
     */
    public function setTemperature(float $temperature): float;

    /**
     * Set the maximum tokens.
     */
    public function setMaxTokens(int $maxTokens): void;

    /**
     * Get the prompt tokens used.
     */
    public function getPromptTokens(): int;

    /**
     * Get the completion text.
     */
    public function getCompletion(): string;

    /**
     * Get the model used.
     */
    public function getModel(): string;

    /**
     * Get the response ID.
     */
    public function getId(): ?string;

    /**
     * Get the completion tokens used.
     */
    public function getCompletionTokens(): string;

    /**
     * Get the total tokens used.
     */
    public function getTotalTokens(): int;

    /**
     * Get the total cost.
     */
    public function getTotalCost(): float;

    /**
     * Get the cost per 1K tokens.
     */
    public function getCostPerToken(): float;

    /**
     * Get the error message.
     */
    public function getError(): ?string;

    /**
     * Track the request log.
     *
     * @param  array<string, mixed>  $response
     */
    public function trackRequestLog(string $bookId, string $chapterId, string $userId, string $itemType, array $response): void;
}
