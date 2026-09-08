<?php
// routes/api/cron.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Déclencheurs planifiés — remplacent `schedule:run` en environnement
// serverless (Vercel Cron), où aucun démon cron ne tourne.
//
// Sécurité : Vercel Cron envoie l'en-tête `Authorization: Bearer $CRON_SECRET`.
// Toute requête sans ce secret est rejetée en 401 — ces routes exécutent des
// tâches lourdes (SMS, facturation) et ne doivent jamais être publiques.
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/**
 * Commandes autorisées, mappées depuis app/Console/Kernel.php.
 * Liste blanche stricte : impossible de déclencher une commande arbitraire.
 */
$commandesPlanifiees = [
    'sms-absents'             => 'edugest:sms-absents',
    'generer-seances'         => 'edugest:generer-seances',
    'relances-impayes'        => 'edugest:relances-impayes',
    'alertes-stock'           => 'edugest:alertes-stock',
    'alertes-preventif'       => 'edugest:alertes-preventif',
    'diagnostic-hebdomadaire' => 'edugest:diagnostic-hebdomadaire',
    'prune'                   => 'model:prune',
];

Route::prefix('cron')->group(function () use ($commandesPlanifiees) {

    Route::get('/{tache}', function (Request $request, string $tache) use ($commandesPlanifiees) {

        $secret = config('services.cron.secret');

        // Fail-closed : si aucun secret n'est configuré, la route est inerte.
        if (empty($secret)) {
            Log::error('Cron: CRON_SECRET non configuré — exécution refusée', ['tache' => $tache]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CRON_NOT_CONFIGURED', 'message' => 'Cron non configuré'],
            ], 503);
        }

        $fourni = (string) $request->bearerToken();

        if (!hash_equals($secret, $fourni)) {
            Log::warning('Cron: tentative non autorisée', [
                'tache' => $tache,
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'UNAUTHORIZED', 'message' => 'Non autorisé'],
            ], 401);
        }

        if (!isset($commandesPlanifiees[$tache])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TACHE_INCONNUE', 'message' => 'Tâche planifiée inconnue'],
            ], 404);
        }

        $commande = $commandesPlanifiees[$tache];
        $debut    = microtime(true);

        try {
            $code   = Artisan::call($commande);
            $sortie = Artisan::output();

            Log::info('Cron: tâche exécutée', [
                'tache'    => $tache,
                'commande' => $commande,
                'code'     => $code,
                'duree_ms' => round((microtime(true) - $debut) * 1000),
            ]);

            return response()->json([
                'success' => $code === 0,
                'data'    => [
                    'tache'    => $tache,
                    'commande' => $commande,
                    'code'     => $code,
                    'duree_ms' => round((microtime(true) - $debut) * 1000),
                    'sortie'   => trim($sortie),
                ],
            ], $code === 0 ? 200 : 500);

        } catch (\Throwable $e) {
            Log::error('Cron: échec de la tâche', [
                'tache'   => $tache,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CRON_ECHEC', 'message' => $e->getMessage()],
            ], 500);
        }
    })->where('tache', '[a-z\-]+');

});
