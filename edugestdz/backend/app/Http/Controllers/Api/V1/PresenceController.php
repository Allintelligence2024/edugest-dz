<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Seance, Presence, Eleve};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

class PresenceController extends Controller
{
    public function saisir(Request $request, string $seanceId): JsonResponse
    {
        $seance = Seance::with('cours.groupe')->findOrFail($seanceId);

        $request->validate([
            'presences'                => 'required|array',
            'presences.*.eleve_id'     => 'required|uuid|exists:eleves,id',
            'presences.*.statut'       => 'required|in:présent,absent,retard,excusé',
            'presences.*.heure_arrivee'=> 'nullable|date_format:H:i',
            'presences.*.motif'        => 'nullable|string|max:200',
        ]);

        $sauvegardees = 0;

        DB::transaction(function () use ($request, $seance, &$sauvegardees) {
            foreach ($request->presences as $p) {
                Presence::updateOrCreate(
                    ['seance_id' => $seance->id, 'eleve_id' => $p['eleve_id']],
                    [
                        'tenant_id'     => config('tenant.current_id'),
                        'statut'        => $p['statut'],
                        'heure_arrivee' => $p['heure_arrivee'] ?? null,
                        'motif'         => $p['motif'] ?? null,
                        'saisi_par'     => auth('api')->id(),
                    ]
                );
                $sauvegardees++;
            }
        });

        if ($seance->statut === 'planifiée') {
            $seance->update(['statut' => 'terminée']);
        }

        return response()->json([
            'success' => true,
            'message' => "{$sauvegardees} présence(s) enregistrée(s)",
        ]);
    }

    public function parSeance(string $seanceId): JsonResponse
    {
        $seance  = Seance::with('cours.groupe')->findOrFail($seanceId);
        $presences = Presence::with('eleve')
            ->where('seance_id', $seanceId)->get()->keyBy('eleve_id');

        $eleves = Eleve::whereHas('inscriptions', fn($q) =>
            $q->where('groupe_id', $seance->cours->groupe_id)
              ->where('statut', 'validée')
        )->get();

        $data = $eleves->map(fn($eleve) => [
            'eleve_id'     => $eleve->id,
            'nom_complet'  => $eleve->nom_complet,
            'photo_url'    => $eleve->photo_url_full,
            'statut'       => $presences[$eleve->id]?->statut ?? null,
            'heure_arrivee'=> $presences[$eleve->id]?->heure_arrivee,
            'motif'        => $presences[$eleve->id]?->motif,
        ]);

        $stats = [
            'total'    => $eleves->count(),
            'presents' => $presences->whereIn('statut', ['présent','retard'])->count(),
            'absents'  => $presences->where('statut', 'absent')->count(),
            'retards'  => $presences->where('statut', 'retard')->count(),
            'excuses'  => $presences->where('statut', 'excusé')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
            'seance'  => [
                'id'          => $seance->id,
                'date'        => $seance->date_seance,
                'heure_debut' => $seance->heure_debut ?? $seance->cours->heure_debut,
                'groupe'      => $seance->cours->groupe->nom,
            ],
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $presence = Presence::findOrFail($id);
        $presence->update($request->validate([
            'statut'       => 'sometimes|in:présent,absent,retard,excusé',
            'motif'        => 'nullable|string|max:200',
            'heure_arrivee'=> 'nullable|date_format:H:i',
        ]));
        return response()->json(['success' => true, 'data' => $presence]);
    }

    public function rapport(Request $request): JsonResponse
    {
        $request->validate([
            'groupe_id' => 'nullable|uuid',
            'eleve_id'  => 'nullable|uuid',
            'mois'      => 'nullable|integer|between:1,12',
            'annee'     => 'nullable|integer',
        ]);

        $query = Presence::with(['eleve', 'seance.cours.groupe'])
            ->when($request->eleve_id, fn($q) => $q->where('eleve_id', $request->eleve_id))
            ->when($request->groupe_id, fn($q) =>
                $q->whereHas('seance.cours', fn($sq) =>
                    $sq->where('groupe_id', $request->groupe_id)
                )
            )
            ->when($request->mois,  fn($q) => $q->whereMonth('created_at', $request->mois))
            ->when($request->annee, fn($q) => $q->whereYear('created_at',  $request->annee));

        $presences = $query->get();
        $total     = $presences->count();

        return response()->json([
            'success' => true,
            'stats'   => [
                'total'    => $total,
                'presents' => $presences->whereIn('statut', ['présent','retard'])->count(),
                'absents'  => $presences->where('statut', 'absent')->count(),
                'taux'     => $total > 0
                    ? round(($presences->whereIn('statut', ['présent','retard'])->count() / $total) * 100, 1)
                    : 0,
            ],
        ]);
    }
}
