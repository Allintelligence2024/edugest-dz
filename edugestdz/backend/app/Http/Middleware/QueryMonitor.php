<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compte les requêtes SQL d'un cycle HTTP et signale les dépassements.
 *
 * Deux changements par rapport à la version d'origine (Sprint 4) :
 *
 *  1. Les seuils viennent de `config/performance.php` au lieu d'être codés
 *     en dur. La suite de tests de performance lit la même configuration :
 *     le budget d'un endpoint est déclaré à un seul endroit, et un
 *     dépassement fait échouer la CI au lieu de produire un log ignoré.
 *
 *  2. L'écouteur `DB::listen` n'est enregistré qu'UNE fois par processus.
 *     L'ancienne version en ajoutait un par requête : sans effet visible en
 *     production (un process = une requête), mais dans une suite de tests
 *     qui enchaîne des centaines d'appels HTTP dans le même process, les
 *     closures s'accumulaient et chaque requête SQL était notifiée à toutes
 *     — coût quadratique et mémoire qui grimpe.
 */
class QueryMonitor
{
    /**
     * Application pour laquelle l'écouteur est enregistré.
     *
     * Un simple booléen ne suffit pas : chaque test PHPUnit reconstruit une
     * application — donc un nouveau dispatcher d'événements — et l'écouteur
     * posé lors du test précédent disparaît avec l'ancien conteneur. Le
     * drapeau restant à `true`, plus aucune requête n'était comptée à partir
     * du deuxième test du processus (`X-Query-Count: 0`). On mémorise donc
     * l'instance, en référence faible pour ne pas la maintenir en vie.
     */
    private static ?\WeakReference $appEcoutee = null;

    /** Requêtes du cycle HTTP courant. */
    private static array $requetes = [];

    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production') || !config('performance.monitor.active', true)) {
            return $next($request);
        }

        $this->demarrerEcoute();

        self::$requetes = [];
        $debut = microtime(true);

        $response = $next($request);

        $requetes    = self::$requetes;
        $dureeTotale = round((microtime(true) - $debut) * 1000, 1);
        $nbRequetes  = count($requetes);

        $seuilLente  = (int) config('performance.monitor.seuil_requete_lente_ms', 100);
        $nbLentes    = count(array_filter($requetes, fn ($q) => $q['time'] > $seuilLente));

        $budget   = $this->budgetPour($request);
        $seuilMs  = (int) config('performance.monitor.seuil_ms', 500);

        if ($nbRequetes > $budget || $dureeTotale > $seuilMs) {
            Log::warning('[QueryMonitor] Budget de performance dépassé', [
                'route'           => $request->path(),
                'nb_requetes'     => $nbRequetes,
                'budget_requetes' => $budget,
                'total_ms'        => $dureeTotale,
                'seuil_ms'        => $seuilMs,
                'requetes_lentes' => $nbLentes,
            ]);
        }

        $response->headers->set('X-Query-Count', (string) $nbRequetes);
        $response->headers->set('X-Query-Budget', (string) $budget);
        $response->headers->set('X-Response-Time', $dureeTotale . 'ms');

        return $response;
    }

    /**
     * Budget applicable à la route courante : valeur dédiée si déclarée,
     * budget global sinon.
     */
    private function budgetPour(Request $request): int
    {
        $budgets = (array) config('performance.budgets', []);

        return (int) ($budgets[$request->path()] ?? config('performance.monitor.budget_requetes', 20));
    }

    /**
     * Nombre de requêtes SQL du dernier cycle HTTP observé.
     *
     * Utilisé par les tests de performance pour recouper la valeur de
     * l'en-tête `X-Query-Count`.
     */
    public static function nbRequetesDernierCycle(): int
    {
        return count(self::$requetes);
    }

    /** @return list<array{sql: string, time: float}> */
    public static function requetesDernierCycle(): array
    {
        return self::$requetes;
    }

    private function demarrerEcoute(): void
    {
        $app = app();

        if (self::$appEcoutee?->get() === $app) {
            return;
        }

        self::$appEcoutee = \WeakReference::create($app);

        DB::listen(function ($query) {
            self::$requetes[] = [
                'sql'  => $query->sql,
                'time' => $query->time,
            ];
        });
    }
}
