<?php

namespace App\Services;

/**
 * Sprint 6 § 4 — Observabilité Sentry.
 *
 * Point d'entrée unique pour signaler à Sentry les événements de sécurité
 * qui ne sont pas des exceptions (le SDK capture déjà les exceptions en
 * production, cf. bootstrap/app.php).
 *
 * Trois règles :
 *  1. jamais bloquant : l'observabilité ne doit pas faire échouer le
 *     traitement métier (try/catch silencieux) ;
 *  2. sans effet si non configuré : pas de SENTRY_DSN → retour immédiat ;
 *  3. statique : appelable depuis les services, middlewares et commandes
 *     sans injection ni modification des constructeurs existants.
 */
class SentryObservabilite
{
    /**
     * Signaler un événement de sécurité à Sentry.
     *
     * @param string $niveau  'info' | 'warning' | 'error' | 'critical' | 'emergency'
     * @param array<string, mixed> $contexte
     */
    public static function capturer(string $message, string $niveau = 'warning', array $contexte = []): void
    {
        try {
            if (empty(config('sentry.dsn')) || !app()->bound('sentry')) {
                return;
            }

            $hub = app('sentry');
            if (!$hub instanceof \Sentry\State\HubInterface) {
                return;
            }

            $severity = match ($niveau) {
                'critical', 'emergency' => \Sentry\Severity::fatal(),
                'error'                 => \Sentry\Severity::error(),
                'warning'               => \Sentry\Severity::warning(),
                default                 => \Sentry\Severity::info(),
            };

            // json_encode() peut retourner false (malformé UTF-8) : sans ce
            // garde, la concaténation échouerait — et la télémétrie ne doit
            // jamais lever d'erreur.
            $contexteJson = json_encode(
                $contexte,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $contenu = ($contexteJson === false || $contexteJson === '[]')
                ? $message
                : $message . ' — ' . $contexteJson;

            $hub->captureMessage($contenu, $severity);
        } catch (\Throwable) {
            // Ne jamais faire échouer l'appelant pour un problème de télémétrie.
        }
    }
}
