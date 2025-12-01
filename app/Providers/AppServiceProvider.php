<?php

namespace App\Providers;

use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiApiServiceInterface;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerAiServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register AI services and bindings.
     */
    protected function registerAiServices(): void
    {
        $this->app->singleton(AiManager::class, function ($app) {
            return new AiManager($app);
        });

        $this->app->bind(AiChatServiceInterface::class, function ($app) {
            return $app->make(AiManager::class)->chat();
        });

        $this->app->bind(AiApiServiceInterface::class, function ($app) {
            return $app->make(AiManager::class)->api();
        });

        $this->app->when(\App\Services\Ai\OpenAi\ChatService::class)
            ->needs(AiApiServiceInterface::class)
            ->give(\App\Services\Ai\OpenAi\ApiService::class);

        $this->app->when(\App\Services\Ai\Llama\ChatService::class)
            ->needs(AiApiServiceInterface::class)
            ->give(\App\Services\Ai\Llama\ApiService::class);

        $this->app->bind(\App\Services\OpenAi\ChatService::class, function ($app) {
            return new \App\Services\OpenAi\ChatService(
                $app->make(\App\Services\OpenAi\ApiService::class),
                $app->make(\App\Services\RequestLog\RequestLogService::class)
            );
        });
    }
}
