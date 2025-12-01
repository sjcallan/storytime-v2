<?php

namespace App\Services\Ai\Facades;

use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiApiServiceInterface;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * AI Facade for easy access to AI services.
 *
 * @method static AiChatServiceInterface chat()
 * @method static AiApiServiceInterface api(?string $provider = null)
 * @method static AiManager provider(string $name)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static array getAvailableProviders()
 * @method static bool hasProvider(string $provider)
 *
 * @see \App\Services\Ai\AiManager
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiManager::class;
    }
}
