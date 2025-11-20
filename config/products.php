<?php

return [
    'training' => [
        'url' => env('TRAINING_PLATFORM_URL', 'http://localhost:5000'),
        'webhook_url' => env('TRAINING_WEBHOOK_URL', 'http://localhost:5000/api/keycloak/setup'),
    ],

    'services' => [
        'url' => env('SERVICES_PLATFORM_URL', 'http://localhost:7000'),
        'webhook_url' => env('SERVICES_WEBHOOK_URL', 'http://localhost:7000/api/keycloak/setup'),
    ],

    'kayan_erp' => [
        'url' => env('KAYAN_ERP_URL', 'http://localhost:5002'),
        'webhook_url' => env('KAYAN_ERP_WEBHOOK_URL', 'http://localhost:5002/api/keycloak/setup'),
    ],
];
