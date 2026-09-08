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

];
