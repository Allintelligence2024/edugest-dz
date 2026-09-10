<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Seance;
use App\Models\Enseignant;
use App\Services\SuggestionRemplacantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RemplacementController extends BaseApiController
{

    public function seancesOrphelines(Request $request): JsonResponse
    {
        $seances = Seance::with(['cours.groupe.matiere', 'cours.enseignant'])
            ->whereNull('enseignant_remplacement_id')
            ->where('statut', 'planifiée')
            ->whereDate('date_seance', '>=', now()->toDateString())
            ->orderBy('date_seance')
            ->orderBy('heure_debut')
            ->get();

        return $this->success($seances);
    }

    public function suggestions(Request $request, string $seanceId): JsonResponse
    {
        $service = app(SuggestionRemplacantService::class);
        $limit   = min((int) $request->query('limit', 5), 20);

        return $this->success($service->suggestions($seanceId, $limit));
    }

    public function confirmer(Request $request, string $seanceId): JsonResponse
    {
        $validated = $request->validate([
            'enseignant_remplacement_id' => 'required|uuid|exists:enseignants,id',
            'notifier' => 'boolean',
        ]);

        $seance = Seance::findOrFail($seanceId);
        $seance->update([
            'enseignant_remplacement_id' => $validated['enseignant_remplacement_id'],
            'statut' => 'remplacement',
        ]);

        if ($validated['notifier'] ?? false) {
            $remplacant = Enseignant::find($validated['enseignant_remplacement_id']);
            Log::info('Notification de remplacement envoyée', [
                'seance_id' => $seanceId,
                'destinataire' => $remplacant?->telephone,
            ]);
        }

        return $this->success(
            $seance->fresh(['cours.enseignant', 'enseignantRemplacement']),
            'Remplacement confirmé'
        );
    }
}
