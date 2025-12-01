<?php

namespace App\Services\Ai\Contracts;

interface AiApiServiceInterface
{
    /**
     * Send a chat completion request with messages.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>|null
     */
    public function chat(array $messages): ?array;

    /**
     * Send a completion request with a prompt.
     *
     * @return array<string, mixed>|null
     */
    public function completion(string $prompt): ?array;

    /**
     * Set the response format.
     *
     * @param  string  $format  - text or json_object
     */
    public function setResponseFormat(string $format): void;

    /**
     * Set the temperature for generation.
     *
     * @param  float  $temperature  0.01 - 1
     */
    public function setTemperature(float $temperature): float;

    /**
     * Set the model to use.
     */
    public function setModel(string $model): void;

    /**
     * Set the maximum tokens for the response.
     */
    public function setMaxTokens(int $maxTokens): void;

    /**
     * Get the HTTP response status code.
     */
    public function getResponseStatusCode(): ?int;

    /**
     * Get the raw response data.
     *
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array;

    /**
     * Get the request data that was sent.
     *
     * @return array<string, mixed>|null
     */
    public function getRequest(): ?array;

    /**
     * Get the response time in seconds.
     */
    public function getResponseTime(): float;

    /**
     * Get the error message if request failed.
     */
    public function getError(): ?string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
