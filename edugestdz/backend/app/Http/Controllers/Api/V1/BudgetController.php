<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BudgetPrevisionnel;
use App\Models\Depense;
use App\Services\BudgetBilanService;
use App\Services\BudgetDashboardService;
use App\Services\BudgetPrevisionnelService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends BaseApiController
{
    public function __construct(
        private BudgetDashboardService $dashboardService,
        private BudgetBilanService $bilanService,
        private BudgetPrevisionnelService $previsionnelService,
    ) {}

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

        $data = $this->dashboardService->getDashboard($mois, $annee);

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

        $date = Carbon::parse($validated['date_depense']);
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
            $date = Carbon::parse($validated['date_depense']);
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

        return $this->success(
            $this->previsionnelService->previsionnel($annee, $mois),
            "Budget prévisionnel {$annee}"
        );
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

        return $this->success(
            $this->bilanService->bilanMensuel($mois, $annee),
            "Bilan {$mois}/{$annee}"
        );
    }

    public function bilanAnnuel(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);

        return $this->success(
            $this->bilanService->bilanAnnuel($annee),
            "Bilan annuel {$annee}"
        );
    }

    public function categories(): JsonResponse
    {
        return $this->success(
            $this->previsionnelService->categories(),
            'Categories de depenses'
        );
    }
}