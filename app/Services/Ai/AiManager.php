<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiApiServiceInterface;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use Illuminate\Support\Manager;

/**
 * AI Manager - Gateway for swapping AI service providers.
 *
 * Use this manager to easily swap between different AI providers (OpenAI, Llama, etc.).
 *
 * @example
 * // Get the default chat service
 * $chatService = app(AiManager::class)->chat();
 *
 * // Get a specific provider's chat service
 * $llamaChat = app(AiManager::class)->provider('llama')->chat();
 *
 * // Use the default provider
 * $response = app(AiManager::class)->chat()->chat();
 */
class AiManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'openai');
    }

    /**
     * Create the OpenAI driver.
     */
    public function createOpenaiDriver(): AiChatServiceInterface
    {
        return $this->container->make(OpenAi\ChatService::class);
    }

    /**
     * Create the Llama driver.
     */
    public function createLlamaDriver(): AiChatServiceInterface
    {
        return $this->container->make(Llama\ChatService::class);
    }

    /**
     * Get the chat service for the current provider.
     */
    public function chat(): AiChatServiceInterface
    {
        return $this->driver();
    }

    /**
     * Get the API service for a specific provider.
     */
    public function api(?string $provider = null): AiApiServiceInterface
    {
        $provider = $provider ?? $this->getDefaultDriver();

        return match ($provider) {
            'llama' => $this->container->make(Llama\ApiService::class),
            default => $this->container->make(OpenAi\ApiService::class),
        };
    }

    /**
     * Get a specific provider's services.
     *
     * @return $this
     */
    public function provider(string $name): static
    {
        $this->setDefaultDriver($name);

        return $this;
    }

    /**
     * Set the default driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->config->set('ai.default', $name);
    }

    /**
     * Get all available providers.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->config->get('ai.providers', []));
    }

    /**
     * Check if a provider is available.
     */
    public function hasProvider(string $provider): bool
    {
        return in_array($provider, $this->getAvailableProviders());
    }
}
