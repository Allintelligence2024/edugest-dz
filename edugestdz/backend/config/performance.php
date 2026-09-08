<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Surveillance des requêtes SQL
    |--------------------------------------------------------------------------
    |
    | `QueryMonitor` compte les requêtes émises pendant un cycle HTTP et les
    | expose dans les en-têtes `X-Query-Count` / `X-Response-Time`. Jusqu'ici
    | il se contentait d'un `Log::warning` avec des seuils codés en dur : une
    | régression N+1 pouvait donc entrer en production sans que rien ne casse.
    |
    | Les seuils sont désormais déclarés ici, ce qui permet à la suite de
    | tests (`tests/Feature/Performance/BudgetRequetesEndpointsTest.php`) de
    | s'appuyer sur la MÊME table de vérité que le middleware. Un budget
    | dépassé fait échouer la CI au lieu de produire une ligne de log que
    | personne ne lit.
    |
    */
    'monitor' => [

        // Désactivable pour un profilage manuel ou un banc de charge.
        'active' => (bool) env('PERF_MONITOR', true),

        // Budget par défaut, appliqué à toute route sans budget dédié.
        'budget_requetes' => (int) env('PERF_BUDGET_REQUETES', 20),

        // Au-delà, la réponse complète est considérée trop lente (ms).
        'seuil_ms' => (int) env('PERF_SEUIL_MS', 500),

        // Une requête unitaire dépassant ce seuil est comptée « lente » (ms).
        'seuil_requete_lente_ms' => (int) env('PERF_SEUIL_REQUETE_LENTE_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Budgets de requêtes par endpoint
    |--------------------------------------------------------------------------
    |
    | Clé = chemin de la route tel que renvoyé par `$request->path()`
    | (sans slash initial), valeur = nombre maximum de requêtes SQL tolérées
    | pour un cycle complet.
    |
    | Ces plafonds sont volontairement larges : ils servent de garde-fou
    | contre une explosion (jointure oubliée, boucle sur une relation), pas
    | de mesure fine. La détection précise des N+1 est faite par comparaison
    | de charge — le nombre de requêtes ne doit PAS croître avec le nombre
    | d'enregistrements retournés. Un plafond absolu ne détecterait pas un
    | N+1 sur une petite collection ; c'est pour cela que les deux mécanismes
    | coexistent.
    |
    */
    'budgets' => [
        'api/v1/eleves'      => 40,
        'api/v1/factures'    => 40,
        'api/v1/groupes'     => 40,
        'api/v1/enseignants' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tolérance de croissance (détection N+1)
    |--------------------------------------------------------------------------
    |
    | Écart maximum admis entre le nombre de requêtes d'un endpoint mesuré
    | avec 1 enregistrement puis avec N. Un eager loading correct donne 0 ;
    | on tolère 1 pour absorber une requête de comptage conditionnelle.
    |
    */
    'tolerance_croissance' => (int) env('PERF_TOLERANCE_CROISSANCE', 1),

];
