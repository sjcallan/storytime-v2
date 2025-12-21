<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | application. You can easily switch between providers by changing this
    | value to any of the configured providers below.
    |
    | Supported: "openai", "llama"
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers used by your application.
    | Each provider has its own configuration options.
    |
    */

    'providers' => [

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4000),
            'temperature' => env('OPENAI_TEMPERATURE', 1),
            'cost_per_1k_tokens' => 0.002,
        ],

        'llama' => [
            'driver' => 'llama',
            'base_url' => env('LLAMA_BASE_URL', 'http://host.docker.internal:5009'),
            'endpoint' => env('LLAMA_ENDPOINT', '/generate'),
            'model' => env('LLAMA_MODEL', 'llama-3.2'),
            'max_tokens' => env('LLAMA_MAX_TOKENS', 4000),
            'temperature' => env('LLAMA_TEMPERATURE', 1),
            'cost_per_1k_tokens' => 0.0, // Local model, no cost
            'timeout' => env('LLAMA_TIMEOUT', 300),
            'token' => env('LLAMA_TOKEN'),
        ],

    ],

];
