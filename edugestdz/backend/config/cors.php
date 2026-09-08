<?php

return [
    'paths'                    => ['api/*', 'modules/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => array_filter([
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', ''),
    ]),
    'allowed_origins_patterns' => [
        // Déploiement : Vercel uniquement (Railway abandonné — 2026-09).
        '#^https://.*\.vercel\.app$#',
        '#^https://edugest.*\.vercel\.app$#',
    ],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['X-Query-Count', 'X-Response-Time'],
    'max_age'                  => 86400,
    /*
    | Obligatoire depuis le Sprint 3 : le refresh token voyage dans un cookie
    | httpOnly, que le navigateur ne transmet que si les credentials sont
    | autorisés. Sur Vercel, frontend et API partagent la même origine, donc
    | le CORS n'intervient qu'en développement local.
    |
    | NB : avec credentials, le navigateur REFUSE le joker '*' en
    | Access-Control-Allow-Origin — d'où la liste explicite ci-dessus.
    */
    'supports_credentials'     => true,
];
