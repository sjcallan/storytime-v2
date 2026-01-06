<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'llama' => [
        'base_url' => env('LLAMA_BASE_URL', 'http://127.0.0.1:5009'),
        'endpoint' => env('LLAMA_ENDPOINT', '/generate'),
    ],

    'replicate' => [
        'api_key' => env('REPLICATE_API_KEY'),
        'use_custom_model' => env('REPLICATE_USE_CUSTOM_MODEL', false),
        'custom_model_version' => env('REPLICATE_CUSTOM_MODEL_VERSION', ''),
        'custom_model_lora' => env('REPLICATE_CUSTOM_MODEL_LORA', ''),
        'custom_model_lora_scale' => env('REPLICATE_CUSTOM_MODEL_LORA_SCALE', 1),
    ],

];
