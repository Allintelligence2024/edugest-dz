<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Services\PredictionEchecService;
use App\Services\ProfilApprentissageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PredictionController extends Controller
{
    public function __construct(
        private PredictionEchecService $predictionService,
        private ProfilApprentissageService $profilService,
    ) {}

    public function predireEleve(Request $request, string $eleveId): JsonResponse
    {
        try {
            $eleve = Eleve::findOrFail($eleveId);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $horizon = $request->input('horizon', '4_semaines');

        $prediction = $this->predictionService->predire($eleveId, $horizon);

        $profil = $this->profilService->calculerProfil($eleveId);

        $liensParentaux = collect();
        try {
            $liensParentaux = DB::table('liens_parentaux')
                ->where('eleve_id', $eleveId)
                ->get();
        } catch (\Throwable $e) {
            if ($request->user()?->role === 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé — données parentales indisponibles',
                ], 403);
            }
        }

        $historique = collect();
        try {
            $historique = DB::table('predictions_echec')
                ->where('eleve_id', $eleveId)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        } catch (\Throwable $e) {
            // Transaction may be poisoned by prior queries — return empty history
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'eleve' => [
                    'id'               => $eleve->id,
                    'nom'              => $eleve->nom,
                    'prenom'           => $eleve->prenom,
                    'niveau_scolaire'  => $eleve->niveau_scolaire,
                ],
                'prediction'         => $prediction,
                'profil_apprentissage' => $profil,
                'liens_parentaux'    => $liensParentaux,
                'historique'         => $historique,
            ],
            'message' => 'Prédiction calculée avec succès',
        ]);
    }

    public function classementRisque(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $niveau = $request->input('niveau');
        $page   = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        $query = DB::table('predictions_echec as p')
            ->join('eleves as e', 'p.eleve_id', '=', 'e.id')
            ->where('p.tenant_id', $tenantId)
            ->where('e.statut', 'actif')
            ->select(
                'p.eleve_id',
                'p.probabilite_echec',
                'p.confiance',
                'p.niveau_risque',
                'p.horizon',
                'p.facteurs_risque',
                'p.recommandations',
                'p.created_at as predit_le',
                'e.nom',
                'e.prenom',
                'e.niveau_scolaire'
            )
            ->orderByDesc('p.probabilite_echec');

        if ($niveau) {
            $query->where('p.niveau_risque', $niveau);
        }

        $total = $query->clone()->count('p.eleve_id');
        $predictions = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $stats = DB::table('predictions_echec')
            ->where('tenant_id', $tenantId)
            ->selectRaw('niveau_risque, COUNT(*) as total, AVG(probabilite_echec) as proba_moyenne')
            ->groupBy('niveau_risque')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'predictions' => $predictions,
                'stats'       => $stats,
            ],
            'meta' => [
                'total'     => $total,
                'page'      => (int) $page,
                'per_page'  => (int) $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'message' => 'Classement de risque récupéré',
        ]);
    }

    public function predireTous(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $horizon = $request->input('horizon', '4_semaines');

        $predictions = $this->predictionService->predireTenant($tenantId);

        $stats = collect($predictions)->groupBy('niveau_risque')->map(fn($g) => $g->count());

        return response()->json([
            'success' => true,
            'data'    => [
                'predictions' => $predictions,
                'stats'       => $stats,
                'total'       => count($predictions),
            ],
            'message' => count($predictions) . ' prédictions calculées',
        ]);
    }
}
