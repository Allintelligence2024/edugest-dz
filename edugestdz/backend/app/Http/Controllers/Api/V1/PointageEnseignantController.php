<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PointageEnseignantController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    public function aujourdhui(): JsonResponse
    {
        $today       = today();
        $enseignants = Enseignant::with(['pointages' => fn($q) => $q->whereDate('date', $today)])
            ->where('statut', 'actif')
            ->get();

        $data = $enseignants->map(function (Enseignant $e) use ($today) {
            $pointage = $e->pointages->first();

            return [
                'enseignant'    => [
                    'id'     => $e->id,
                    'nom'    => $e->nom,
                    'prenom' => $e->prenom,
                    'photo'  => $e->photo_url,
                ],
                'statut'        => $pointage?->statut ?? 'absent',
                'heure_arrivee' => $pointage?->heure_arrivee,
                'heure_depart'  => $pointage?->heure_depart,
                'methode'       => $pointage?->methode,
                'pointe'        => (bool) $pointage,
            ];
        });

        $stats = [
            'total'    => $enseignants->count(),
            'presents' => $data->where('statut', 'present')->count(),
            'absents'  => $data->where('statut', 'absent')->count(),
            'retards'  => $data->where('statut', 'retard')->count(),
        ];

        return $this->success([
            'date'        => $today->format('d/m/Y'),
            'enseignants' => $data,
            'stats'       => $stats,
        ], "Pointage du {$today->format('d/m/Y')}");
    }

    public function arrivee(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_arrivee' => 'nullable|date_format:H:i',
            'note'          => 'nullable|string|max:500',
        ]);

        $enseignant = Enseignant::findOrFail($id);
        $today      = today();
        $heure      = $validated['heure_arrivee'] ?? now()->format('H:i');

        $existant = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereDate('date', $today)
            ->whereNotNull('heure_arrivee')
            ->first();

        if ($existant) {
            return $this->error(
                "Arrivée déjà enregistrée à {$existant->heure_arrivee}",
                'DEJA_POINTE',
                409
            );
        }

        $heureLimite = config('etablissement.heure_limite_retard_prof', '08:15');
        $enRetard    = $heure > $heureLimite;

        $pointage = PointageEnseignant::create([
            'tenant_id'      => config('tenant.current_id'),
            'enseignant_id'  => $enseignant->id,
            'date'           => $today,
            'heure_arrivee'  => $heure,
            'methode'        => 'manuel',
            'statut'         => $enRetard ? 'retard' : 'present',
            'note'           => $validated['note'] ?? null,
        ]);

        return $this->created([
            'pointage'   => $pointage,
            'enseignant' => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
            'statut'     => $pointage->statut,
        ], "Arrivée enregistrée : {$enseignant->prenom} {$enseignant->nom} à {$heure}");
    }

    public function depart(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_depart' => 'nullable|date_format:H:i',
            'note'         => 'nullable|string|max:500',
        ]);

        $enseignant = Enseignant::findOrFail($id);
        $today      = today();
        $heure      = $validated['heure_depart'] ?? now()->format('H:i');

        $pointage = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereDate('date', $today)
            ->first();

        if (!$pointage) {
            return $this->error(
                'Aucune arrivée enregistrée aujourd\'hui. Enregistrez d\'abord l\'arrivée.',
                'PAS_ARRIVEE',
                422
            );
        }

        if ($pointage->heure_depart) {
            return $this->error(
                "Départ déjà enregistré à {$pointage->heure_depart}",
                'DEJA_POINTE',
                409
            );
        }

        $pointage->update([
            'heure_depart' => $heure,
            'note'         => $validated['note'] ?? $pointage->note,
        ]);

        return $this->success([
            'pointage'        => $pointage->fresh(),
            'duree_minutes'   => $pointage->fresh()->duree_travaillee,
            'enseignant'      => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
        ], "Départ enregistré : {$enseignant->prenom} {$enseignant->nom} à {$heure}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $enseignant = Enseignant::findOrFail($id);

        $debut = $validated['debut'] ?? now()->startOfMonth()->toDateString();
        $fin   = $validated['fin']   ?? now()->toDateString();

        $paginator = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereBetween('date', [$debut, $fin])
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 30);

        $items = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereBetween('date', [$debut, $fin])
            ->get();

        $stats = [
            'total_jours'   => $items->count(),
            'presents'      => $items->where('statut', 'present')->count(),
            'absents'       => $items->where('statut', 'absent')->count(),
            'retards'       => $items->where('statut', 'retard')->count(),
            'conges'        => $items->where('statut', 'conge')->count(),
            'maladies'      => $items->where('statut', 'maladie')->count(),
            'duree_totale_minutes' => $items->sum(fn($p) => $p->duree_travaillee ?? 0),
        ];

        return $this->paginatedResponse($paginator, 'Historique récupéré', [
            'enseignant' => ['id' => $enseignant->id, 'nom' => "{$enseignant->nom} {$enseignant->prenom}"],
            'periode'    => ['debut' => $debut, 'fin' => $fin],
            'stats'      => $stats,
        ]);
    }
}
