<?php

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => array_filter([
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', ''),
    ]),
    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
        '#^https://edugest.*\.vercel\.app$#',
    ],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['X-Query-Count', 'X-Response-Time'],
    'max_age'                  => 86400,
    'supports_credentials'     => false,
];
