<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Outillage de mesure du coût SQL d'un appel HTTP.
 *
 * Deux façons complémentaires de cadrer la performance d'un endpoint :
 *
 *  • `assertBudgetRequetes()` — plafond absolu. Attrape l'explosion franche
 *    (jointure oubliée, boucle sur une relation non chargée).
 *
 *  • `assertPasDeN1()` — plafond différentiel, le plus fiable des deux. On
 *    mesure l'endpoint avec 1 enregistrement, puis avec N, et on exige que
 *    le nombre de requêtes ne bouge pas. C'est la définition littérale d'un
 *    N+1, et elle ne dépend d'aucun nombre magique : un refactoring qui
 *    ajoute une requête constante ne fera pas rougir la CI à tort, alors
 *    qu'une relation chargée en boucle sera prise immédiatement.
 *
 * Les messages d'échec sont volontairement bavards (compteurs, requêtes
 * fautives dédupliquées). L'expérience de ce dépôt est qu'un échec de CI
 * lisible vaut plusieurs allers-retours de 4 minutes.
 */
trait BudgetRequetes
{
    /**
     * Exécute $action en journalisant les requêtes SQL émises.
     *
     * @return array{nb:int, sql:list<string>, resultat:mixed}
     */
    protected function mesurerRequetes(callable $action): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $resultat = $action();

        $journal = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return [
            'nb'       => count($journal),
            'sql'      => array_map(fn ($e) => $e['query'], $journal),
            'resultat' => $resultat,
        ];
    }

    /**
     * Vérifie qu'un appel reste sous un plafond absolu de requêtes.
     *
     * @return array{nb:int, sql:list<string>, resultat:mixed}
     */
    protected function assertBudgetRequetes(int $budget, callable $action, string $contexte): array
    {
        $mesure = $this->mesurerRequetes($action);

        $this->assertLessThanOrEqual(
            $budget,
            $mesure['nb'],
            "Budget dépassé pour {$contexte} : {$mesure['nb']} requêtes pour un budget de {$budget}. "
            . 'Répartition : ' . $this->repartitionParTable($mesure['sql'])
        );

        return $mesure;
    }

    /**
     * Répartition compacte des requêtes par table, la plus fréquente d'abord.
     *
     * @param list<string> $sql
     */
    private function repartitionParTable(array $sql): string
    {
        $compteur = [];
        foreach ($sql as $requete) {
            if (preg_match('/(?:from|into|update|join)\s+"?([a-z0-9_]+)"?/i', $requete, $m)) {
                $compteur[$m[1]] = ($compteur[$m[1]] ?? 0) + 1;
            }
        }

        arsort($compteur);
        $extrait = array_slice($compteur, 0, 8, true);

        $morceaux = [];
        foreach ($extrait as $table => $nb) {
            $morceaux[] = "{$table}×{$nb}";
        }

        return implode(', ', $morceaux);
    }

    /**
     * Vérifie que le coût SQL d'un endpoint ne croît pas avec le volume.
     *
     * @param callable(int):void $creer  Crée $n enregistrements supplémentaires.
     * @param callable():mixed   $appel  Effectue l'appel à mesurer.
     */
    protected function assertPasDeN1(
        callable $creer,
        callable $appel,
        string $contexte,
        int $volume = 5,
        ?int $tolerance = null,
    ): void {
        $tolerance ??= (int) config('performance.tolerance_croissance', 1);

        // 1 enregistrement : référence.
        $creer(1);

        // Appel de chauffe, non mesuré — il absorbe les coûts non liés au
        // volume (résolution du tenant, mise en cache de statistiques,
        // vérification d'abonnement) qui ne se produisent qu'une fois.
        $appel();

        $reference = $this->mesurerRequetes($appel);

        // Montée en volume.
        $creer($volume - 1);

        // Seconde chauffe, indispensable et non évidente : créer des
        // enregistrements invalide les caches qui en dépendent. Sur
        // /api/v1/eleves, `EleveObserver` purge `eleves_stats_{tenant}` à
        // chaque création ; sans cette chauffe, la mesure sous charge payait
        // la reconstitution du cache (trois agrégats) et l'écart était
        // imputé à tort à un N+1. On compare deux états stables, pas un état
        // chaud contre un état froid.
        $appel();

        $charge = $this->mesurerRequetes($appel);

        $croissance = $charge['nb'] - $reference['nb'];

        $this->assertLessThanOrEqual(
            $tolerance,
            $croissance,
            // Message volontairement compact et hiérarchisé : les annotations
            // GitHub sont découpées en tranches de ~220 caractères, donc
            // l'information la plus discriminante doit venir en premier.
            "N+1 sur {$contexte} : {$reference['nb']} → {$charge['nb']} requêtes "
            . "quand les lignes passent de 1 à {$volume} (tolérance {$tolerance}). "
            . 'Tables en cause : ' . $this->deltasParTable($reference['sql'], $charge['sql']) . '. '
            . 'Détail : ' . implode(' | ', array_slice($this->requetesEnTrop($reference['sql'], $charge['sql']), 0, 3))
        );
    }

    /**
     * Compare le nombre de requêtes par table entre les deux mesures.
     *
     * Bien plus lisible qu'un dump de SQL : le nom de la table qui passe de
     * 1 à 5 requêtes désigne immédiatement la relation non chargée.
     *
     * @param list<string> $reference
     * @param list<string> $charge
     */
    private function deltasParTable(array $reference, array $charge): string
    {
        $parTable = static function (array $sql): array {
            $compteur = [];
            foreach ($sql as $requete) {
                if (preg_match('/(?:from|into|update|join)\s+"?([a-z0-9_]+)"?/i', $requete, $m)) {
                    $table = $m[1];
                    $compteur[$table] = ($compteur[$table] ?? 0) + 1;
                }
            }

            return $compteur;
        };

        $avant = $parTable($reference);
        $apres = $parTable($charge);

        $deltas = [];
        foreach ($apres as $table => $nb) {
            $ancien = $avant[$table] ?? 0;
            if ($nb > $ancien) {
                $deltas[] = "{$table} {$ancien}→{$nb}";
            }
        }

        return $deltas === [] ? 'aucune (croissance hors requêtes identifiables)' : implode(', ', $deltas);
    }

    /**
     * Requêtes présentes plus souvent sous charge qu'à la référence.
     *
     * @param  list<string> $reference
     * @param  list<string> $charge
     * @return list<string>
     */
    private function requetesEnTrop(array $reference, array $charge): array
    {
        $compter = static function (array $sql): array {
            $compteur = [];
            foreach ($sql as $requete) {
                $normalisee = preg_replace('/\s+/', ' ', trim($requete));
                $compteur[$normalisee] = ($compteur[$normalisee] ?? 0) + 1;
            }

            return $compteur;
        };

        $avant = $compter($reference);
        $apres = $compter($charge);

        $enTrop = [];
        foreach ($apres as $requete => $nb) {
            $delta = $nb - ($avant[$requete] ?? 0);
            if ($delta > 0) {
                // Empreinte courte : le début d'une requête suffit à
                // l'identifier, et les annotations CI sont tronquées.
                $enTrop[] = "×{$delta} " . mb_substr($requete, 0, 70);
            }
        }

        return $enTrop;
    }

}
