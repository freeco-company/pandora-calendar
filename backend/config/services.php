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

    'pandora_core' => [
        'base_url' => env('PANDORA_CORE_BASE_URL'),
        'internal_secret' => env('PANDORA_CORE_INTERNAL_SECRET'),
        'jwt_issuer' => env('PANDORA_CORE_JWT_ISSUER', 'pandora-core'),
        'jwt_audience' => env('PANDORA_CORE_JWT_AUDIENCE', 'fairy-calendar'),
        'product_code' => env('PANDORA_CORE_PRODUCT_CODE', 'fairy-calendar'),
        'webhook_secret' => env('PANDORA_CORE_WEBHOOK_SECRET'),
        'webhook_window_seconds' => (int) env('PANDORA_CORE_WEBHOOK_WINDOW_SECONDS', 300),
    ],

];
