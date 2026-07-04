<?php

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://localhost:5173',
        'http://localhost:3000',
        'https://edugest-dz.vercel.app',
        'https://*.vercel.app',
        env('FRONTEND_URL', ''),
    ],
    'allowed_origins_patterns' => ['#^https://edugest-dz.*\.vercel\.app$#'],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [
        'X-Query-Count', 'X-Response-Time',
    ],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];
