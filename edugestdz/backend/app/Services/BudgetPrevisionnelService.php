<?php

namespace App\Services;

use App\Models\Depense;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Budget prévisionnel : consultation, saisie, référentiel des catégories.
 *
 * La consultation sans « mois » est volontairement asymétrique, héritée de
 * l'historique du contrôleur :
 *   - les prévisions sont filtrées sur `mois` (un mois nul dans la requête
 *     sélectionne les prévisions annuelles, qui portent mois = NULL) ;
 *   - les réalisés, eux, sont agrégés sur toute l'année lorsque le mois est
 *     absent.
 */
class BudgetPrevisionnelService
{
    public const CATEGORIES = [
        'salaires_enseignants', 'salaires_personnel', 'loyer',
        'electricite_gaz', 'eau', 'telephone_internet',
        'fournitures_bureau', 'fournitures_pedagogiques',
        'maintenance_reparation', 'assurance', 'publicite_marketing',
        'transport', 'cantine_restauration', 'taxes_impots', 'autres',
    ];

    /**
     * @return Collection<int, array{code: string, libelle: string}>
     */
    public function categories(): Collection
    {
        return collect(self::CATEGORIES)->map(fn (string $categorie): array => [
            'code'    => $categorie,
            'libelle' => Depense::categorieLibelle($categorie),
        ]);
    }

    /**
     * @return array{annee: int, mois: int|null, lignes: Collection<int, array{
     *               categorie: string, libelle: string, prevu: float,
     *               realise: float, ecart: float, pct_realise: float|null}>,
     *               total_prevu: float, total_realise: float, ecart_total: float}
     */
    public function previsionnel(int $annee, ?int $mois): array
    {
        /** @var Collection<string, object{categorie: string, montant_prevu: float|null}> $previsions */
        $previsions = $this->table('budget_previsionnel')
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->get(['categorie', 'montant_prevu'])
            ->keyBy('categorie');

        $requeteRealises = $this->table('depenses')
            ->where('statut', 'validee')
            ->where('annee', $annee)
            ->selectRaw('categorie, SUM(montant) AS total_realise')
            ->groupBy('categorie');

        if ($mois !== null) {
            $requeteRealises->where('mois', $mois);
        }

        /** @var Collection<string, object{categorie: string, total_realise: string}> $realises */
        $realises = $requeteRealises->get()->keyBy('categorie');

        $lignes = collect(self::CATEGORIES)->map(function (string $categorie) use ($previsions, $realises): array {
            $prevu   = (float) ($previsions[$categorie]->montant_prevu ?? 0);
            $realise = (float) ($realises[$categorie]->total_realise ?? 0);

            return [
                'categorie'   => $categorie,
                'libelle'     => Depense::categorieLibelle($categorie),
                'prevu'       => $prevu,
                'realise'     => $realise,
                'ecart'       => $prevu - $realise,
                'pct_realise' => $prevu > 0 ? round(($realise / $prevu) * 100, 1) : null,
            ];
        });

        return [
            'annee'         => $annee,
            'mois'          => $mois,
            'lignes'        => $lignes,
            'total_prevu'   => (float) $lignes->sum('prevu'),
            'total_realise' => (float) $lignes->sum('realise'),
            'ecart_total'   => (float) $lignes->sum('ecart'),
        ];
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