<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BudgetPrevisionnel;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/budget/dashboard",
     *     summary="Dashboard budget (recettes, dépenses, résultat net)",
     *     tags={"Budget"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="mois",  in="query", @OA\Schema(type="integer", example=7)),
     *     @OA\Parameter(name="annee", in="query", @OA\Schema(type="integer", example=2026)),
     *     @OA\Response(
     *         response=200,
     *         description="Données budget du mois",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="recettes",    type="number", format="float"),
     *                 @OA\Property(property="depenses",    type="number", format="float"),
     *                 @OA\Property(property="resultat_net",type="number", format="float"),
     *                 @OA\Property(property="impayes",     type="number", format="float"),
     *                 @OA\Property(property="evolution",   type="array",  @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);
        $key   = "budget_dashboard_" . config('tenant.current_id') . "_{$mois}_{$annee}";

        $data = cache()->remember($key, 600, function () use ($mois, $annee) {
            $recettes = (float) Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $mois)
                ->whereYear('date_paiement', $annee)
                ->sum('montant');

            $depenses = (float) Depense::validees()
                ->periode($mois, $annee)
                ->sum('montant');

            $impayes = (float) Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->where('date_echeance', '<', today())
                ->sum('total_ttc');

            $parCategorie = Depense::validees()
                ->periode($mois, $annee)
                ->selectRaw('categorie, SUM(montant) as total')
                ->groupBy('categorie')
                ->get()
                ->mapWithKeys(fn($r) => [
                    $r->categorie => [
                        'libelle' => Depense::categorieLibelle($r->categorie),
                        'total'   => (float) $r->total,
                        'prevu'   => BudgetPrevisionnel::getPrevision($r->categorie, now()->year, $mois),
                    ],
                ]);

            $evolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $evolution[] = [
                    'label'    => $date->translatedFormat('M Y'),
                    'recettes' => (float) Paiement::where('statut', 'confirmé')
                        ->whereMonth('date_paiement', $date->month)
                        ->whereYear('date_paiement', $date->year)
                        ->sum('montant'),
                    'depenses' => (float) Depense::validees()
                        ->periode($date->month, $date->year)
                        ->sum('montant'),
                ];
                $evolution[count($evolution) - 1]['resultat'] =
                    $evolution[count($evolution) - 1]['recettes'] - $evolution[count($evolution) - 1]['depenses'];
            }

            return [
                'recettes'      => $recettes,
                'depenses'      => $depenses,
                'resultat_net'  => $recettes - $depenses,
                'impayes'       => $impayes,
                'par_categorie' => $parCategorie,
                'evolution'     => $evolution,
            ];
        });

        return $this->success(array_merge($data, ['periode' => compact('mois', 'annee')]), "Dashboard budget {$mois}/{$annee}");
    }

    public function indexDepenses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'      => 'nullable|integer|min:1|max:12',
            'annee'     => 'nullable|integer|min:2020|max:2030',
            'categorie' => 'nullable|string',
            'statut'    => 'nullable|in:en_attente,validee,rejetee',
            'search'    => 'nullable|string|max:100',
            'per_page'  => 'nullable|integer|min:5|max:100',
        ]);

        $query = Depense::with('saisiePar:id,nom,prenom')
            ->orderByDesc('date_depense');

        if (!empty($validated['mois']) && !empty($validated['annee'])) {
            $query->periode($validated['mois'], $validated['annee']);
        } elseif (!empty($validated['annee'])) {
            $query->annee($validated['annee']);
        }

        if (!empty($validated['categorie'])) {
            $query->where('categorie', $validated['categorie']);
        }
        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('libelle', 'like', "%{$validated['search']}%")
                  ->orWhere('fournisseur', 'like', "%{$validated['search']}%");
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 20);

        $totalSelection = Depense::when(!empty($validated['mois']) && !empty($validated['annee']),
            fn($q) => $q->periode($validated['mois'], $validated['annee'])
        )->when(!empty($validated['categorie']),
            fn($q) => $q->where('categorie', $validated['categorie'])
        )->validees()->sum('montant');

        return $this->paginatedResponse($paginator, 'Dépenses récupérées', [
            'total_selection' => (float) $totalSelection,
        ]);
    }

    public function storeDepense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categorie'          => 'required|in:salaires_enseignants,salaires_personnel,loyer,electricite_gaz,eau,telephone_internet,fournitures_bureau,fournitures_pedagogiques,maintenance_reparation,assurance,publicite_marketing,transport,cantine_restauration,taxes_impots,autres',
            'libelle'            => 'required|string|max:200',
            'montant'            => 'required|numeric|min:0.01',
            'date_depense'       => 'required|date',
            'fournisseur'        => 'nullable|string|max:150',
            'numero_facture_ext' => 'nullable|string|max:100',
            'mode_paiement'      => 'nullable|in:cash,virement,cheque,cib',
            'note'               => 'nullable|string|max:500',
        ]);

        $date = \Carbon\Carbon::parse($validated['date_depense']);
        $validated['mois']       = $date->month;
        $validated['annee']      = $date->year;
        $validated['saisie_par'] = auth()->id();
        $validated['statut']     = 'validee';

        $depense = Depense::create($validated);

        cache()->forget("budget_dashboard_" . config('tenant.current_id') . "_{$validated['mois']}_{$validated['annee']}");

        return $this->created([
            'depense'           => $depense,
            'categorie_libelle' => Depense::categorieLibelle($depense->categorie),
        ], "Depense enregistree : {$depense->libelle}");
    }

    public function updateDepense(Request $request, string $id): JsonResponse
    {
        $depense = Depense::findOrFail($id);
        $validated = $request->validate([
            'categorie'     => 'sometimes|in:salaires_enseignants,salaires_personnel,loyer,electricite_gaz,eau,telephone_internet,fournitures_bureau,fournitures_pedagogiques,maintenance_reparation,assurance,publicite_marketing,transport,cantine_restauration,taxes_impots,autres',
            'libelle'       => 'sometimes|string|max:200',
            'montant'       => 'sometimes|numeric|min:0.01',
            'date_depense'  => 'sometimes|date',
            'fournisseur'   => 'nullable|string|max:150',
            'mode_paiement' => 'nullable|in:cash,virement,cheque,cib',
            'statut'        => 'sometimes|in:en_attente,validee,rejetee',
            'note'          => 'nullable|string|max:500',
        ]);

        if (isset($validated['date_depense'])) {
            $date = \Carbon\Carbon::parse($validated['date_depense']);
            $validated['mois']  = $date->month;
            $validated['annee'] = $date->year;
        }

        $depense->update($validated);

        return $this->success($depense->fresh(), 'Depense mise a jour');
    }

    public function destroyDepense(string $id): JsonResponse
    {
        $depense = Depense::findOrFail($id);
        $libelle = $depense->libelle;

        cache()->forget("budget_dashboard_" . config('tenant.current_id') . "_{$depense->mois}_{$depense->annee}");

        $depense->delete();

        return $this->success(null, "Depense '{$libelle}' supprimee");
    }

    public function uploadJustificatif(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'justificatif' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $depense = Depense::findOrFail($id);
        $path = $request->file('justificatif')->store(
            'depenses/' . config('tenant.current_id'),
            'public'
        );

        $depense->update(['justificatif_url' => $path]);

        return $this->success(
            ['justificatif_url' => $path],
            'Justificatif uploadé'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/budget/previsionnel",
     *     summary="Budget prévisionnel vs réalisé par catégorie",
     *     tags={"Budget"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer", example=2026)),
     *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Prévisionnel vs réalisé", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function previsionnel(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);
        $mois  = $request->filled('mois') ? (int) $request->mois : null;

        $categories = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'eau', 'telephone_internet',
            'fournitures_bureau', 'fournitures_pedagogiques',
            'maintenance_reparation', 'assurance', 'publicite_marketing',
            'transport', 'cantine_restauration', 'taxes_impots', 'autres',
        ];

        $previsions = BudgetPrevisionnel::where('annee', $annee)
            ->where('mois', $mois)
            ->get()
            ->keyBy('categorie');

        $realises = Depense::validees()
            ->where('annee', $annee)
            ->when($mois, fn($q) => $q->where('mois', $mois))
            ->selectRaw('categorie, SUM(montant) as total_realise')
            ->groupBy('categorie')
            ->get()
            ->keyBy('categorie');

        $data = collect($categories)->map(function (string $cat) use ($previsions, $realises) {
            $prevu   = (float) ($previsions[$cat]?->montant_prevu ?? 0);
            $realise = (float) ($realises[$cat]?->total_realise   ?? 0);

            return [
                'categorie'   => $cat,
                'libelle'     => Depense::categorieLibelle($cat),
                'prevu'       => $prevu,
                'realise'     => $realise,
                'ecart'       => $prevu - $realise,
                'pct_realise' => $prevu > 0 ? round(($realise / $prevu) * 100, 1) : null,
            ];
        });

        return $this->success([
            'annee'         => $annee,
            'mois'          => $mois,
            'lignes'        => $data,
            'total_prevu'   => $data->sum('prevu'),
            'total_realise' => $data->sum('realise'),
            'ecart_total'   => $data->sum('ecart'),
        ], "Budget prévisionnel {$annee}");
    }

    public function setPrevisionnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'annee'                => 'required|integer|min:2020|max:2030',
            'mois'                 => 'nullable|integer|min:1|max:12',
            'lignes'               => 'required|array|min:1',
            'lignes.*.categorie'   => 'required|string',
            'lignes.*.montant_prevu' => 'required|numeric|min:0',
            'lignes.*.note'        => 'nullable|string|max:300',
        ]);

        $enregistres = 0;
        foreach ($validated['lignes'] as $ligne) {
            BudgetPrevisionnel::updateOrCreate(
                [
                    'tenant_id' => config('tenant.current_id'),
                    'annee'     => $validated['annee'],
                    'mois'      => $validated['mois'] ?? null,
                    'categorie' => $ligne['categorie'],
                ],
                [
                    'montant_prevu' => $ligne['montant_prevu'],
                    'note'          => $ligne['note'] ?? null,
                ]
            );
            $enregistres++;
        }

        return $this->success(
            ['enregistres' => $enregistres],
            "{$enregistres} ligne(s) de budget enregistree(s)"
        );
    }

    public function bilanMensuel(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);

        $recettes = (float) Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $depenses = (float) Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        $depensesDetail = Depense::validees()
            ->periode($mois, $annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->map(fn($r) => [
                'categorie' => $r->categorie,
                'libelle'   => Depense::categorieLibelle($r->categorie),
                'total'     => (float) $r->total,
            ]);

        $facturesEmises = (float) Facture::whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->sum('total_ttc');

        return $this->success([
            'periode'           => compact('mois', 'annee'),
            'recettes'          => $recettes,
            'factures_emises'   => $facturesEmises,
            'depenses'          => $depenses,
            'resultat_net'      => $recettes - $depenses,
            'taux_recouvrement' => $facturesEmises > 0
                ? round(($recettes / $facturesEmises) * 100, 1) : 0,
            'depenses_detail'   => $depensesDetail,
        ], "Bilan {$mois}/{$annee}");
    }

    public function bilanAnnuel(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);

        $data = [];
        $totalRecettes = 0;
        $totalDepenses = 0;

        for ($m = 1; $m <= 12; $m++) {
            $rec = (float) Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $m)
                ->whereYear('date_paiement', $annee)
                ->sum('montant');

            $dep = (float) Depense::validees()
                ->periode($m, $annee)
                ->sum('montant');

            $totalRecettes += $rec;
            $totalDepenses += $dep;

            $data[] = [
                'mois'     => $m,
                'label'    => \Carbon\Carbon::create($annee, $m, 1)->translatedFormat('F'),
                'recettes' => $rec,
                'depenses' => $dep,
                'resultat' => $rec - $dep,
            ];
        }

        $depensesParCategorie = Depense::validees()
            ->annee($annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->map(fn($r) => [
                'categorie' => $r->categorie,
                'libelle'   => Depense::categorieLibelle($r->categorie),
                'total'     => (float) $r->total,
                'pct'       => $totalDepenses > 0
                    ? round(($r->total / $totalDepenses) * 100, 1) : 0,
            ]);

        return $this->success([
            'annee'                  => $annee,
            'mois_par_mois'          => $data,
            'total_recettes'         => $totalRecettes,
            'total_depenses'         => $totalDepenses,
            'resultat_annuel'        => $totalRecettes - $totalDepenses,
            'depenses_par_categorie' => $depensesParCategorie,
        ], "Bilan annuel {$annee}");
    }

    public function categories(): JsonResponse
    {
        $cats = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'eau', 'telephone_internet',
            'fournitures_bureau', 'fournitures_pedagogiques',
            'maintenance_reparation', 'assurance', 'publicite_marketing',
            'transport', 'cantine_restauration', 'taxes_impots', 'autres',
        ];

        return $this->success(
            collect($cats)->map(fn($c) => [
                'code'    => $c,
                'libelle' => Depense::categorieLibelle($c),
            ]),
            'Categories de depenses'
        );
    }
}
