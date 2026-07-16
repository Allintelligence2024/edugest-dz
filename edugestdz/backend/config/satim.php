<?php

/*
|--------------------------------------------------------------------------
| SATIM Payment Gateway Configuration
|--------------------------------------------------------------------------
|
| SATUT ACTUEL : SANDBOX (mode test)
| Documentation complète : docs/SATIM_PRODUCTION_CHECKLIST.md
|
| Pour passer en production, suivre la checklist dans docs/SATIM_PRODUCTION_CHECKLIST.md
| et régler SATIM_SANDBOX=false dans .env
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Terminal ID
    |--------------------------------------------------------------------------
    |
    | Identifiant du terminal attribué par SATIM lors de l'enregistrement.
    |
    */
    'terminal_id' => env('SATIM_TERMINAL_ID'),

    /*
    |--------------------------------------------------------------------------
    | Merchant ID
    |--------------------------------------------------------------------------
    |
    | Identifiant du commerçant attribué par SATIM.
    |
    */
    'merchant_id' => env('SATIM_MERCHANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    |
    | Mot de passe du terminal SATIM. À stocker dans les variables d'env Railway.
    |
    */
    'password' => env('SATIM_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | URL de base de l'API SATIM.
    | SANDBOX  : https://test.satim.dz/payment/rest
    | PROD     : https://satim.dz/payment/rest
    |
    */
    'url' => env('SATIM_URL', 'https://test.satim.dz/payment/rest'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | true  = mode test (SANDBOX) — transactions simulées
    | false = mode production — transactions réelles débitées
    |
    | ⚠️ NE PAS DÉSACTIVER EN PRODUCTION sans validation SATIM complète
    |
    | Documentation : docs/SATIM_PRODUCTION_CHECKLIST.md
    |
    */
    'sandbox' => env('SATIM_SANDBOX', true),

];
