<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'tabstack' => [
        'key' => env('TABSTACK_API_KEY'),
        'base_url' => env('TABSTACK_BASE_URL', 'https://api.tabstack.ai/v1'),
        'timeout' => (int) env('TABSTACK_TIMEOUT', 900),
        'fetch_timeout' => env('TABSTACK_FETCH_TIMEOUT') ? (int) env('TABSTACK_FETCH_TIMEOUT') : null,
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'gemma4:latest'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 900),
    ],

];
