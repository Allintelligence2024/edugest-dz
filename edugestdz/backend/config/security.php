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
    'qr' => [
        'signing_key' => env('QR_SIGNING_KEY') ?: env('APP_KEY', 'insecure-dev-qr-key'),

        // Durée de validité d'un jeton QR en jours (null = pas d'expiration).
        // Un badge scolaire vit typiquement une année scolaire.
        'ttl_days' => env('QR_TTL_DAYS', 400),
    ],

];
