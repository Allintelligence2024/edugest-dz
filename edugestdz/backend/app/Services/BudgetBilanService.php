<?php

namespace App\Services;

use App\Models\Depense;
use App\Models\Facture;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bilans mensuel et annuel du budget.
 *
 * L'ancien bilan annuel émettait 24 requêtes (boucle de 12 mois × deux
 * sommations) ; il tient désormais en deux requêtes GROUP BY.
 */
class BudgetBilanService
{
    /**
     * @return array{periode: array{mois: int, annee: int}, recettes: float,
     *               factures_emises: float, depenses: float, resultat_net: float,
     *               taux_recouvrement: float|int, depenses_detail: Collection<int, array{categorie: string, libelle: string, total: float}>}
     */
    public function bilanMensuel(int $mois, int $annee): array
    {
        $recettes = (float) Paiement::confirmes()
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $depenses = (float) Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        /** @var Collection<int, object{categorie: string, total: string}> $lignes */
        $lignes = Depense::validees()
            ->periode($mois, $annee)
            ->selectRaw('categorie, SUM(montant) AS total')
            ->groupBy('categorie')
            ->get();

        $depensesDetail = $lignes->map(fn ($ligne): array => [
            'categorie' => $ligne->categorie,
            'libelle'   => Depense::categorieLibelle($ligne->categorie),
            'total'     => (float) $ligne->total,
        ]);

        $facturesEmises = (float) Facture::whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->sum('total_ttc');

        return [
            'periode'           => ['mois' => $mois, 'annee' => $annee],
            'recettes'          => $recettes,
            'factures_emises'   => $facturesEmises,
            'depenses'          => $depenses,
            'resultat_net'      => $recettes - $depenses,
            'taux_recouvrement' => $facturesEmises > 0
                ? round(($recettes / $facturesEmises) * 100, 1)
                : 0,
            'depenses_detail'   => $depensesDetail,
        ];
    }

    /**
     * @return array{annee: int, mois_par_mois: list<array{mois: int, label: string,
     *               recettes: float, depenses: float, resultat: float}>,
     *               total_recettes: float, total_depenses: float, resultat_annuel: float,
     *               depenses_par_categorie: Collection<int, array{categorie: string, libelle: string, total: float, pct: float}>}
     */
    public function bilanAnnuel(int $annee): array
    {
        /** @var Collection<int, object{mois: string, total: string}> $recettesParMois */
        $recettesParMois = Paiement::confirmes()
            ->whereYear('date_paiement', $annee)
            ->selectRaw('EXTRACT(MONTH FROM date_paiement) AS mois, SUM(montant) AS total')
            ->groupByRaw('EXTRACT(MONTH FROM date_paiement)')
            ->get()
            ->keyBy(fn ($ligne): int => (int) $ligne->mois);

        /** @var Collection<int, object{mois: int, total: string}> $depensesParMois */
        $depensesParMois = Depense::validees()
            ->annee($annee)
            ->selectRaw('mois, SUM(montant) AS total')
            ->groupBy('mois')
            ->get()
            ->keyBy(fn ($ligne): int => (int) $ligne->mois);

        $moisParMois = [];
        $totalRecettes = 0.0;
        $totalDepenses = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $rec = (float) ($recettesParMois[$m]?->total ?? 0);
            $dep = (float) ($depensesParMois[$m]?->total ?? 0);

            $totalRecettes += $rec;
            $totalDepenses += $dep;

            $moisParMois[] = [
                'mois'     => $m,
                'label'    => Carbon::create($annee, $m, 1)->translatedFormat('F'),
                'recettes' => $rec,
                'depenses' => $dep,
                'resultat' => $rec - $dep,
            ];
        }

        /** @var Collection<int, object{categorie: string, total: string}> $lignes */
        $lignes = Depense::validees()
            ->annee($annee)
            ->selectRaw('categorie, SUM(montant) AS total')
            ->groupBy('categorie')
            ->get();

        $depensesParCategorie = $lignes->map(fn ($ligne): array => [
            'categorie' => $ligne->categorie,
            'libelle'   => Depense::categorieLibelle($ligne->categorie),
            'total'     => (float) $ligne->total,
            'pct'       => $totalDepenses > 0
                ? round(((float) $ligne->total / $totalDepenses) * 100, 1)
                : 0.0,
        ]);

        return [
            'annee'                  => $annee,
            'mois_par_mois'          => $moisParMois,
            'total_recettes'         => $totalRecettes,
            'total_depenses'         => $totalDepenses,
            'resultat_annuel'        => $totalRecettes - $totalDepenses,
            'depenses_par_categorie' => $depensesParCategorie,
        ];
    }
}