<?php

namespace App\Services;

use App\Models\Depense;
use App\Models\Facture;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats du tableau de bord budgétaire (sprint M13).
 *
 * La clé de cache respecte l'historique du contrôleur
 * ("budget_dashboard_{tenant}_{mois}_{annee}") : les mutations de dépenses
 * l'invalident explicitement. Déplacer ou raccourcir cette clé casserait
 * silencieusement la purge du cache à la saisie d'une dépense.
 *
 * Les agrégats sont calculés en quelques requêtes GROUP BY, sans boucle N+1.
 */
class BudgetDashboardService
{
    /**
     * @return array{recettes: float, depenses: float, resultat_net: float,
     *               impayes: float, par_categorie: Collection<string, array{libelle: string, total: float, prevu: float}>,
     *               evolution: Collection<int, array{label: string, recettes: float, depenses: float, resultat: float}>}
     */
    public function getDashboard(int $mois, int $annee): array
    {
        $cle = "budget_dashboard_" . config('tenant.current_id') . "_{$mois}_{$annee}";

        return (array) cache()->remember($cle, 600, fn (): array => $this->calculer($mois, $annee));
    }

    /**
     * @return array{recettes: float, depenses: float, resultat_net: float,
     *               impayes: float, par_categorie: Collection<string, array{libelle: string, total: float, prevu: float}>,
     *               evolution: Collection<int, array{label: string, recettes: float, depenses: float, resultat: float}>}
     */
    private function calculer(int $mois, int $annee): array
    {
        $recettes = (float) Paiement::confirmes()
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $depenses = (float) Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        $impayes = (float) Facture::impayes()
            ->where('date_echeance', '<', today())
            ->sum('total_ttc');

        /** @var Collection<int, object{categorie: string, total: string}> $depensesParCategorie */
        $depensesParCategorie = $this->table('depenses')
            ->where('statut', 'validee')
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->selectRaw('categorie, SUM(montant) AS total')
            ->groupBy('categorie')
            ->get();

        /** @var Collection<string, object{categorie: string, montant_prevu: float|null}> $previsions */
        $previsions = $this->table('budget_previsionnel')
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->get(['categorie', 'montant_prevu'])
            ->keyBy('categorie');

        // Avant : BudgetPrevisionnel::getPrevision() par catégorie, soit une
        // requête SQL supplémentaire par ligne. Une seule requête suffit.
        $parCategorie = $depensesParCategorie->mapWithKeys(function ($ligne) use ($previsions): array {
            $categorie = (string) $ligne->categorie;

            return [
                $categorie => [
                    'libelle' => Depense::categorieLibelle($categorie),
                    'total'   => (float) $ligne->total,
                    'prevu'   => (float) ($previsions[$categorie]->montant_prevu ?? 0.0),
                ],
            ];
        });

        return [
            'recettes'      => $recettes,
            'depenses'      => $depenses,
            'resultat_net'  => $recettes - $depenses,
            'impayes'       => $impayes,
            'par_categorie' => $parCategorie,
            'evolution'     => $this->evolutionSixMois(),
        ];
    }

    /**
     * Tendance sur les 6 derniers mois glissants (de now()-5 à now()).
     *
     * Deux requêtes GROUP BY remplacent l'ancienne boucle de 6 itérations qui
     * émettent deux requêtes SQL par mois (12 requêtes au total).
     *
     * @return Collection<int, array{label: string, recettes: float, depenses: float, resultat: float}>
     */
    private function evolutionSixMois(): Collection
    {
        // Ordre chronologique : le mois le plus ancien d'abord.
        $periodes = collect(range(5, 0))
            ->map(fn (int $i): Carbon => now()->subMonths($i))
            ->map(fn (Carbon $date): array => ['annee' => (int) $date->year, 'mois' => (int) $date->month]);

        /** @var Collection<string, object{annee: string, mois: string, total: string}> $recettesParPeriode */
        $recettesParPeriode = $this->table('paiements')
            ->where('statut', 'confirmé')
            ->where(function (Builder $requete) use ($periodes): void {
                foreach ($periodes as $periode) {
                    $requete->orWhere(fn ($meme) => $meme
                        ->whereMonth('date_paiement', $periode['mois'])
                        ->whereYear('date_paiement', $periode['annee']));
                }
            })
            ->selectRaw('EXTRACT(YEAR FROM date_paiement) AS annee, EXTRACT(MONTH FROM date_paiement) AS mois, SUM(montant) AS total')
            ->groupByRaw('EXTRACT(YEAR FROM date_paiement), EXTRACT(MONTH FROM date_paiement)')
            ->get()
            ->keyBy(fn ($ligne): string => "{$ligne->annee}-{$ligne->mois}");

        /** @var Collection<string, object{annee: string, mois: string, total: string}> $depensesParPeriode */
        $depensesParPeriode = $this->table('depenses')
            ->where('statut', 'validee')
            ->where(function (Builder $requete) use ($periodes): void {
                foreach ($periodes as $periode) {
                    $requete->orWhere(fn ($meme) => $meme
                        ->where('mois', $periode['mois'])
                        ->where('annee', $periode['annee']));
                }
            })
            ->selectRaw('mois, annee, SUM(montant) AS total')
            ->groupBy('mois', 'annee')
            ->get()
            ->keyBy(fn ($ligne): string => "{$ligne->annee}-{$ligne->mois}");

        return $periodes->map(function (array $periode) use ($recettesParPeriode, $depensesParPeriode): array {
            $cle = "{$periode['annee']}-{$periode['mois']}";

            $recettes = (float) ($recettesParPeriode[$cle]->total ?? 0);
            $depenses = (float) ($depensesParPeriode[$cle]->total ?? 0);

            return [
                'label'    => Carbon::create($periode['annee'], $periode['mois'], 1)->translatedFormat('M Y'),
                'recettes' => $recettes,
                'depenses' => $depenses,
                'resultat' => $recettes - $depenses,
            ];
        });
    }

    /**
     * Requête brute sur une table, répliquant le scope tenant (fail-closed)
     * de App\Traits\BelongsToTenant : sans contexte tenant, aucun résultat.
     */
    private function table(string $table): Builder
    {
        $builder = DB::table($table);
        $tenantId = config('tenant.current_id');

        if ($tenantId !== null) {
            $builder->where('tenant_id', $tenantId);
        } else {
            $builder->whereRaw('1 = 0');
        }

        return $builder;
    }
}