<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant courant
    |--------------------------------------------------------------------------
    |
    | Renseigné à l'exécution par le middleware ResolveTenant. La valeur null
    | signifie « aucun contexte résolu » et NE DOIT JAMAIS être interprétée
    | comme « tous les tenants » : le scope global BelongsToTenant applique
    | alors un filtre 1=0 (fail-closed) et la création lève une exception.
    |
    */
    'current_id' => null,

    'column' => env('TENANT_COLUMN', 'tenant_id'),

    /*
    | Contextes autorisés à s'exécuter sans tenant résolu (console, files
    | d'attente, super-admin). En dehors de ceux-ci, l'absence de tenant est
    | une anomalie qui doit être signalée plutôt que silencieusement tolérée.
    */
    'strict' => (bool) env('TENANT_STRICT', true),
];
