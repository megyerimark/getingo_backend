<?php

return [
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


    'code_runner' => [
        'providers' => [
            'python' => env('CODE_RUNNER_PYTHON_PROVIDER', 'judge0'),
            'csharp' => env('CODE_RUNNER_CSHARP_PROVIDER', 'onecompiler'),
            'sql' => env('CODE_RUNNER_SQL_PROVIDER', 'onecompiler'),
        ],
        'fallback_provider' => env('CODE_RUNNER_FALLBACK_PROVIDER', 'judge0'),
    ],

    'judge0' => [
        'url' => env('JUDGE0_URL'),
        'auth_token' => env('JUDGE0_AUTH_TOKEN'),
    ],

    'onecompiler' => [
        'url' => env('ONECOMPILER_URL', 'https://api.onecompiler.com/v1'),
        'api_key' => env('ONECOMPILER_API_KEY'),
    ],

    'piston' => [
        'url' => env('PISTON_URL'),
        'auth_header' => env('PISTON_AUTH_HEADER', 'Authorization'),
        'auth_token' => env('PISTON_AUTH_TOKEN'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'premium_monthly_price_id' => env('STRIPE_PREMIUM_MONTHLY_PRICE_ID'),
        'premium_yearly_price_id' => env('STRIPE_PREMIUM_YEARLY_PRICE_ID'),
    ],
];
