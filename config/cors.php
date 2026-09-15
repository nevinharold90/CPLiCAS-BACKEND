<?php

// This Section right here uses CORS to filter out requests from other origins. This is important for security reasons, as it prevents malicious websites from making requests to your API without your permission.

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Specify your Vite frontend URL (do NOT use '*')
    'allowed_origins' => [
        'http://192.168.0.140:5173',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        // 'http://192.168.0.112:5174',
        // 'http://192.168.0.112:5174',
        'http://172.25.43.106:3000',
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Enable credentials
    'supports_credentials' => true,
];
