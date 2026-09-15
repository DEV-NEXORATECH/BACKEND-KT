<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs', 'api-docs.json', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://127.0.0.1:3000,http://localhost:8080,https://red-lyrebird-398503.hostingersite.com,http://103.103.193.161:90'
    )))),

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'Content-Type'],

    'max_age' => 86400,

    'supports_credentials' => true,
];