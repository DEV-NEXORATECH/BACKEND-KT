<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs', 'api-docs.json', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge(
        array_filter(array_map('trim', explode(',', env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://127.0.0.1:3000,http://localhost:8080'
        )))),
        // Keep the deployed web application allowed even when an old
        // production .env value overrides the default list.
        ['https://kaoemtelapak.nexora-technology.id']
    ))),

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
        '#^https://.*\.hostingersite\.com/?$#',
    ],

    // Keep this explicit because shared-hosting Apache may answer OPTIONS
    // before Laravel and needs to allow the platform audit header too.
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Client-Platform',
        'X-Fiscal-Year-Id',
    ],

    'exposed_headers' => ['Content-Disposition', 'Content-Type'],

    'max_age' => 86400,

    'supports_credentials' => true,
];
