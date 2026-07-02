<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointagePersonnelController extends BaseApiController
{
    public function arrivee(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_arrivee' => 'nullable|date_format:H:i',
            'methode'       => 'nullable|in:badge,manuel',
            'note'          => 'nullable|string|max:300',
        ]);

        $agent = PersonnelNonEnseignant::findOrFail($id);
        $today = today();
        $heure = $validated['heure_arrivee'] ?? now()->format('H:i');

        $dejaPointe = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', $today)
            ->whereNotNull('heure_arrivee')
            ->exists();

        if ($dejaPointe) {
            return $this->error(
                "Arrivée déjà enregistrée pour {$agent->nom_complet}",
                'DEJA_POINTE',
                409
            );
        }

        $heureLimite = config('etablissement.heure_limite_retard_personnel', '08:00');
        $enRetard    = strcmp($heure . ':00', $heureLimite . ':00') > 0;

        $pointage = PointagePersonnel::create([
            'tenant_id'     => config('tenant.current_id'),
            'agent_id'      => $agent->id,
            'date'          => $today,
            'heure_arrivee' => $heure . ':00',
            'methode'       => $validated['methode'] ?? 'manuel',
            'statut'        => $enRetard ? 'retard' : 'present',
            'note'          => $validated['note'] ?? null,
        ]);

        return $this->created([
            'pointage' => $pointage,
            'agent'    => ['nom' => $agent->nom_complet, 'poste' => $agent->poste_affiche],
            'statut'   => $pointage->statut,
        ], "Arrivée : {$agent->nom_complet} à {$heure}");
    }

    public function depart(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_depart' => 'nullable|date_format:H:i',
            'note'         => 'nullable|string|max:300',
        ]);

        $agent    = PersonnelNonEnseignant::findOrFail($id);
        $today    = today();
        $heure    = $validated['heure_depart'] ?? now()->format('H:i');

        $pointage = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', $today)
            ->first();

        if (!$pointage?->heure_arrivee) {
            return $this->error(
                "Enregistrez d'abord l'arrivée",
                'PAS_ARRIVEE',
                422
            );
        }

        if ($pointage->heure_depart) {
            return $this->error(
                'Départ déjà enregistré',
                'DEJA_POINTE',
                409
            );
        }

        $pointage->update(['heure_depart' => $heure . ':00']);
        $duree = $pointage->fresh()->duree_travaillee;

        return $this->success([
            'pointage'      => $pointage->fresh(),
            'duree_minutes' => $duree,
            'duree_affichee'=> $duree ? floor($duree / 60) . 'h' . ($duree % 60) . 'min' : null,
        ], "Départ : {$agent->nom_complet} à {$heure}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $agent  = PersonnelNonEnseignant::findOrFail($id);
        $debut  = $validated['debut'] ?? now()->startOfMonth()->toDateString();
        $fin    = $validated['fin']   ?? today()->toDateString();

        $paginator = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', '>=', $debut)
            ->whereDate('date', '<=', $fin)
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 30);

        $tous     = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', '>=', $debut)
            ->whereDate('date', '<=', $fin)
            ->get();

        $dureeTotal = $tous->sum(fn($p) => $p->duree_travaillee ?? 0);

        return $this->paginatedResponse($paginator, 'Historique pointage personnel', [
            'agent'  => ['nom' => $agent->nom_complet, 'poste' => $agent->poste_affiche],
            'periode'=> ['debut' => $debut, 'fin' => $fin],
            'stats'  => [
                'jours_travailles'     => $tous->whereNotNull('heure_arrivee')->count(),
                'presents'             => $tous->where('statut', 'present')->count(),
                'retards'              => $tous->where('statut', 'retard')->count(),
                'absents'              => $tous->where('statut', 'absent')->count(),
                'duree_totale_minutes' => $dureeTotal,
                'duree_totale_affichee'=> floor($dureeTotal / 60) . 'h' . ($dureeTotal % 60) . 'min',
            ],
        ]);
    }
}
