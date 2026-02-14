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
    | Content Moderation
    |--------------------------------------------------------------------------
    |
    | Enable content moderation for user-submitted text using OpenAI's
    | moderation API. When enabled, all user text inputs will be checked
    | for potentially harmful content before processing.
    |
    */

    'moderation' => [
        'enabled' => env('MODERATION_ENABLED', false),
        'model' => env('MODERATION_MODEL', 'omni-moderation-latest'),
        // Minimum score threshold to consider a flag valid (0.0 - 1.0)
        // OpenAI sometimes flags content with low confidence scores - this overrides those
        'min_threshold' => env('MODERATION_MIN_THRESHOLD', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers used by your application.
    | Each provider has its own configuration options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Image Generation Pricing
    |--------------------------------------------------------------------------
    |
    | Pricing configuration for image generation services. Costs are per image.
    | These values can be updated as pricing changes.
    |
    */

    'image_generation' => [
        'replicate' => [
            'flux_2_pro' => [
                'model' => 'black-forest-labs/flux-2-pro',
                'cost_per_input_image' => 0.015,
                'cost_per_output_image' => 0.015,
            ],
            'flux_2_max' => [
                'model' => 'black-forest-labs/flux-2-max',
                'cost_per_input_image' => 0.03,
                'cost_per_output_image' => 0.04,
            ],
            'flux_krea_dev' => [
                'model' => 'black-forest-labs/flux-krea-dev',
                'cost_per_input_image' => 0.0,
                'cost_per_output_image' => 0.025,
            ],
            'custom_model' => [
                'cost_per_input_image' => 0.0,
                'cost_per_output_image' => 0.025,
            ],
        ],
    ],

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

        'nemotron3' => [
            'driver' => 'nemotron3',
            'api_key' => env('NEMOTRON3_API_KEY', 'sk-no-key-required'),
            'base_url' => env('NEMOTRON3_BASE_URL', 'http://127.0.0.1:8001/v1'),
            'model' => env('NEMOTRON3_MODEL', 'Nemotron-3-Nano'),
            'max_tokens' => env('NEMOTRON3_MAX_TOKENS', 16384),
            'temperature' => env('NEMOTRON3_TEMPERATURE', 0.8),
            'cost_per_1k_tokens' => 0.0, // Local model, no cost
            'timeout' => env('NEMOTRON3_TIMEOUT', 360),
        ],

    ],

];
