<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clé de signature des QR codes élèves
    |--------------------------------------------------------------------------
    |
    | Clé HMAC dédiée à la signature des jetons QR de présence. Volontairement
    | SÉPARÉE de APP_KEY : une rotation de APP_KEY ne doit pas invalider tous
    | les badges déjà imprimés/distribués aux élèves.
    |
    | En production, définir QR_SIGNING_KEY (min. 32 caractères aléatoires).
    | Fallback sur APP_KEY uniquement pour ne pas casser les environnements
    | de dev/test qui ne l'ont pas encore définie.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Chaîne d'audit inviolable
    |--------------------------------------------------------------------------
    |
    | Clé HMAC dédiée à la signature des blocs d'audit. Elle DOIT être
    | distincte de APP_KEY : sinon toute rotation de APP_KEY invaliderait
    | rétroactivement l'intégralité de la chaîne, rendant impossible la
    | démonstration de non-altération — exactement ce que la chaîne existe
    | pour prouver.
    |
    | La rotation est supportée : chaque bloc mémorise la version de clé qui
    | l'a signé (colonne key_version), et les anciennes clés restent
    | déclarées dans `previous_keys` pour permettre la vérification
    | historique.
    |
    */
    'audit' => [
        'key_version' => (int) env('AUDIT_CHAIN_KEY_VERSION', 1),

        'keys' => array_filter([
            1 => env('AUDIT_CHAIN_KEY') ?: env('APP_KEY'),
            2 => env('AUDIT_CHAIN_KEY_V2'),
            3 => env('AUDIT_CHAIN_KEY_V3'),
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentification
    |--------------------------------------------------------------------------
    |
    | Le refresh token voyage dans un cookie httpOnly : inaccessible au
    | JavaScript, donc insensible au vol par XSS — contrairement au stockage
    | en localStorage utilisé jusqu'ici.
    |
    */
    'auth' => [
        'refresh_ttl_days' => (int) env('AUTH_REFRESH_TTL_DAYS', 14),
    ],

    'qr' => [
        'signing_key' => env('QR_SIGNING_KEY') ?: env('APP_KEY', 'insecure-dev-qr-key'),

        // Durée de validité d'un jeton QR en jours (null = pas d'expiration).
        // Un badge scolaire vit typiquement une année scolaire.
        'ttl_days' => env('QR_TTL_DAYS', 400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Honeypot — routes leurres
    |--------------------------------------------------------------------------
    |
    | Chemins qui n'existent pas dans l'application mais que les scanners
    | automatisés interrogent systématiquement. Les atteindre ne prouve pas
    | une intrusion, mais signale une reconnaissance en cours : la requête
    | est journalisée en warning puis répond 404, à l'identique d'un chemin
    | inconnu, pour ne pas révéler que le leurre en est un.
    |
    | Cette liste est la SOURCE UNIQUE. Elle alimente à la fois
    | l'enregistrement des routes (routes/api/honeypot.php) et
    | HoneypotService::getRoutesLeurres().
    |
    | Elle existe parce que l'information était auparavant dupliquée à deux
    | endroits, et que les deux copies avaient divergé : le service déclarait
    | 22 leurres, le fichier de routes n'en enregistrait que 16. Les six
    | absents — .env, admin, debug, backup, config, dump — sont précisément
    | les cibles les plus courantes des scanners. Un test comptait bien
    | « 22 » : il interrogeait l'inventaire, pas les routes réellement
    | servies. Les six manquants sont désormais enregistrés, et
    | HoneypotRoutesTest vérifie que chaque entrée résout vers un leurre.
    |
    | Format : nom court => chemin, relatif à /api. Le nom devient le nom de
    | route, préfixé `honeypot.` — RbacCoverageTest s'appuie sur ce préfixe
    | pour distinguer un leurre d'une route métier non protégée.
    |
    | Avant d'ajouter un chemin, vérifier qu'aucune route réelle ne l'occupe :
    | les leurres sont enregistrés en dernier, une collision rendrait donc le
    | leurre inatteignable plutôt que de casser la route métier — silencieux,
    | et faussement rassurant.
    |
    */
    'honeypot' => [

        // Permet de couper les leurres sur un environnement où ils feraient
        // du bruit (tests de charge, recette). Actifs par défaut.
        'actif' => (bool) env('HONEYPOT_ACTIF', true),

        'routes' => [
            'phpinfo'       => '/v1/phpinfo',
            'server-status' => '/v1/server-status',
            'actuator'      => '/v1/actuator',
            'metrics'       => '/v1/metrics',
            'env'           => '/v1/.env',
            'admin'         => '/v1/admin',
            'debug'         => '/v1/debug',
            'backup'        => '/v1/backup',
            'config'        => '/v1/config',
            'dump'          => '/v1/dump',
            'git-config'    => '/v1/.git/config',
            'swagger'       => '/v1/swagger.json',
            'graphql'       => '/v1/graphql',
            'health-check'  => '/v1/health/check',
            'ping'          => '/v1/ping',
            'test'          => '/v1/test',
            'api-docs'      => '/v1/api-docs',
            'robots'        => '/v1/robots.txt',
            'sitemap'       => '/v1/sitemap.xml',
            'cron'          => '/v1/cron',
            'deploy'        => '/v1/deploy',
            'websocket'     => '/v1/websocket',
        ],
    ],

];
