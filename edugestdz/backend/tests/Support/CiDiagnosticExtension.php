<?php

namespace Tests\Support;

use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Remonte les échecs de tests dans le résumé de job GitHub Actions.
 *
 * Motivation : le téléchargement des logs GitHub Actions échoue de façon
 * persistante depuis l'environnement de travail (le CDN de logs renvoie EOF),
 * et PHP est indisponible localement pour reproduire — ce qui rend un échec
 * de CI totalement opaque. Les diagnostics étaient déduits par lecture
 * statique, avec le taux d'erreur qu'on imagine.
 *
 * Cette extension écrit chaque erreur et chaque échec dans
 * $GITHUB_STEP_SUMMARY et les émet en annotations `::error::`, tous deux
 * lisibles via l'API GitHub même quand les logs ne le sont pas.
 *
 * Sans effet hors CI : si GITHUB_STEP_SUMMARY est absent, rien n'est écrit.
 */
final class CiDiagnosticExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $facade->registerSubscribers(
            new class implements ErroredSubscriber {
                public function notify(Errored $event): void
                {
                    CiDiagnosticExtension::rapporter(
                        'ERREUR',
                        $event->test()->id(),
                        $event->throwable()->message(),
                        $event->throwable()->stackTrace(),
                    );
                }
            },
            new class implements FailedSubscriber {
                public function notify(Failed $event): void
                {
                    CiDiagnosticExtension::rapporter(
                        'ECHEC',
                        $event->test()->id(),
                        $event->throwable()->message(),
                        $event->throwable()->stackTrace(),
                    );
                }
            },
        );
    }

    public static function rapporter(string $type, string $test, string $message, string $trace): void
    {
        $resume = getenv('GITHUB_STEP_SUMMARY');
        if (!$resume) {
            return; // hors CI : ne rien faire
        }

        // Ne garder que les premières lignes de trace pointant vers le code
        // du projet : le reste est du bruit de framework.
        $lignesUtiles = [];
        foreach (explode("\n", $trace) as $ligne) {
            if (str_contains($ligne, '/vendor/')) {
                continue;
            }
            $lignesUtiles[] = trim($ligne);
            if (count($lignesUtiles) >= 4) {
                break;
            }
        }

        $bloc = sprintf(
            "\n<details><summary>%s — %s</summary>\n\n```\n%s\n\n%s\n```\n</details>\n",
            $type,
            $test,
            trim($message),
            implode("\n", array_filter($lignesUtiles)),
        );

        @file_put_contents($resume, $bloc, FILE_APPEND);

        // ── Annotations ───────────────────────────────────────────────────
        // Seul canal de diagnostic réellement lisible depuis l'environnement
        // de travail (les logs Actions renvoient EOF au téléchargement).
        //
        // GitHub tronque le texte d'une annotation aux alentours de 255
        // caractères : un message un peu détaillé — compteurs, valeurs
        // attendues, requêtes fautives — arrivait coupé en plein mot. On le
        // découpe donc en plusieurs annotations numérotées, ce qui permet de
        // transporter un diagnostic complet sans dépendre des logs.
        $court     = trim(preg_replace('/\s+/', ' ', $message));
        $tailleMax = 220;
        $morceaux  = mb_str_split($court, $tailleMax);

        // Plafond : GitHub limite le nombre d'annotations remontées par
        // étape ; inutile de le saturer avec un seul test.
        $morceaux = array_slice($morceaux, 0, 4);
        $total    = count($morceaux);

        foreach ($morceaux as $i => $morceau) {
            $numero = $total > 1 ? sprintf(' %d/%d', $i + 1, $total) : '';

            fwrite(STDOUT, sprintf(
                "::error title=%s%s::%s — %s\n",
                $type,
                $numero,
                $test,
                $morceau,
            ));
        }
    }
}
