<?php

/**
 * config/database.php — EduGest DZ
 *
 * Base de données : PostgreSQL 16 UNIQUEMENT
 *
 * Raisons du choix PostgreSQL exclusif :
 *   - Row-Level Security (RLS) — isolation multi-tenant au niveau BDD
 *   - JSONB — stockage JSON binaire avec indexation GIN
 *   - gen_random_uuid() — UUID natif sans dépendance PHP
 *   - SAVEPOINT — transactions imbriquées pour les migrations sécurité
 *   - SHA3 — fonctions cryptographiques natives
 *   - Performance — parallélisme de requêtes, partitionnement
 *
 * SQLite N'EST PAS SUPPORTÉ — voir docs/ARCHITECTURE.md
 */

return [
    // ── Connexion par défaut ───────────────────────────────────────────
    // Toujours pgsql — pas de fallback possible
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        // ── PostgreSQL 16 — SEULE connexion autorisée ─────────────────
        'pgsql' => [
            'driver'         => 'pgsql',
            'url'            => env('DB_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE', 'edugestdz'),
            'username'       => env('DB_USERNAME', 'edugest_user'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => 'public',
            'sslmode'        => env('DB_SSLMODE', 'prefer'),
            // Options de performance PostgreSQL
            'options'        => [
                // Fuseau horaire Algérie — critique pour les schedulers
                'timezone'   => 'Africa/Algiers',
            ],
        ],

        // ── Connexion de test (identique à pgsql mais base séparée) ───
        // Utilisée par phpunit.xml via DB_DATABASE=edugestdz_test
        'pgsql_test' => [
            'driver'         => 'pgsql',
            'url'            => env('DB_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE_TEST', 'edugestdz_test'),
            'username'       => env('DB_USERNAME', 'edugest_user'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => 'public',
            'sslmode'        => 'prefer',
        ],
    ],

    // ── Table de migrations ────────────────────────────────────────────
    'migrations' => [
        'table'               => 'migrations',
        'update_date_on_publish' => true,
    ],

    // ── Redis ──────────────────────────────────────────────────────────
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster'    => env('REDIS_CLUSTER', 'redis'),
            'prefix'     => env('REDIS_PREFIX', 'edugest_'),
        ],

        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
