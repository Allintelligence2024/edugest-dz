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
            "Budget de requêtes dépassé pour {$contexte} : {$mesure['nb']} requêtes pour un budget de {$budget}.\n"
            . $this->formaterRequetes($mesure['sql'])
        );

        return $mesure;
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

        $charge = $this->mesurerRequetes($appel);

        $croissance = $charge['nb'] - $reference['nb'];

        $this->assertLessThanOrEqual(
            $tolerance,
            $croissance,
            "N+1 détecté sur {$contexte} : le nombre de requêtes croît avec le volume.\n"
            . "  1 enregistrement  → {$reference['nb']} requêtes\n"
            . "  {$volume} enregistrements → {$charge['nb']} requêtes\n"
            . "  croissance {$croissance} (tolérance {$tolerance})\n"
            . "Requêtes apparues ou multipliées :\n"
            . $this->formaterRequetes($this->requetesEnTrop($reference['sql'], $charge['sql']))
        );
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
                $enTrop[] = "×{$delta}  {$requete}";
            }
        }

        return $enTrop;
    }

    /** @param list<string> $requetes */
    private function formaterRequetes(array $requetes, int $max = 15): string
    {
        if ($requetes === []) {
            return "  (aucune requête à signaler)\n";
        }

        $extrait = array_slice($requetes, 0, $max);
        $lignes  = array_map(
            fn ($sql) => '  - ' . mb_substr(preg_replace('/\s+/', ' ', trim($sql)), 0, 220),
            $extrait
        );

        $reste = count($requetes) - count($extrait);
        if ($reste > 0) {
            $lignes[] = "  … et {$reste} autre(s)";
        }

        return implode("\n", $lignes) . "\n";
    }
}
