<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Seance;
use App\Models\Enseignant;
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
        $seance = Seance::with('cours.enseignant')->findOrFail($seanceId);

        $enseignants = Enseignant::where('tenant_id', config('tenant.current_id'))
            ->where('statut', 'actif')
            ->where('id', '!=', $seance->cours->enseignant_id)
            ->get()
            ->map(fn($ens) => [
                'id' => $ens->id,
                'nom' => $ens->nom,
                'prenom' => $ens->prenom,
                'specialite' => $ens->specialite,
            ]);

        return $this->success([
            'seance' => $seance->load('cours.groupe.matiere', 'cours.enseignant'),
            'suggestions' => $enseignants,
        ]);
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
