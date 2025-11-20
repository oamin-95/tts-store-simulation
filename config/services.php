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

    'keycloak' => [
        'url' => env('KEYCLOAK_URL', 'http://localhost:8090'),
        'service_account' => [
            'client_id' => env('KEYCLOAK_SERVICE_CLIENT_ID', 'saas-marketplace-admin'),
            'client_secret' => env('KEYCLOAK_SERVICE_CLIENT_SECRET'),
        ],
    ],

    'products' => [
        'training' => [
            'url' => env('TRAINING_PLATFORM_URL', 'http://localhost:5000'),
            'webhook_url' => env('TRAINING_PLATFORM_WEBHOOK_URL', 'http://localhost:5000/api/keycloak/setup'),
        ],
        'services' => [
            'url' => env('SERVICES_PLATFORM_URL', 'http://localhost:7000'),
            'webhook_url' => env('SERVICES_PLATFORM_WEBHOOK_URL', 'http://localhost:7000/api/keycloak/setup'),
        ],
    ],

];
