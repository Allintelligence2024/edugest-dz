<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlanFractionnement;
use App\Models\TrancheFractionnement;
use App\Services\FacturationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanFractionnementController extends Controller
{
    public function __construct(
        private FacturationService $facturationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = PlanFractionnement::with(['tranches', 'eleve', 'facture'])
            ->when($request->eleve_id, fn($q, $v) => $q->where('eleve_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facture_id' => 'required|uuid|exists:factures,id',
            'nb_tranches' => 'required|integer|min:2|max:12',
            'echeances' => 'nullable|array',
            'echeances.*' => 'date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $plan = $this->facturationService->creerPlanFractionnement($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plan de fractionnement créé avec succès',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $plan = PlanFractionnement::with(['tranches', 'eleve', 'facture'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function affecter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|uuid|exists:plans_fractionnement,id',
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ]);

        $plan = PlanFractionnement::findOrFail($validated['plan_id']);

        if ($plan->eleve_id === $validated['eleve_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Ce plan est déjà affecté à cet élève',
            ], 422);
        }

        $plan = $this->facturationService->affecterPlanAEleve(
            $validated['plan_id'],
            $validated['eleve_id']
        );

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plan affecté à l\'élève avec succès',
        ], 201);
    }

    public function annuler(string $id): JsonResponse
    {
        $plan = PlanFractionnement::findOrFail($id);
        $plan->update(['statut' => 'annulé']);
        $plan->tranches()->update(['statut' => 'annulée']);

        return response()->json([
            'success' => true,
            'message' => 'Plan annulé',
        ]);
    }
}
