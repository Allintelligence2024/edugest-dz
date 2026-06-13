<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Seance, Presence};
use Illuminate\Http\{Request, JsonResponse};

class SeanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seances = Seance::with(['cours.enseignant', 'cours.groupe.matiere', 'cours.salle'])
            ->when($request->cours_id, fn($q) => $q->where('cours_id', $request->cours_id))
            ->when($request->date,     fn($q) => $q->where('date_seance', $request->date))
            ->when($request->statut,   fn($q) => $q->where('statut', $request->statut))
            ->whereBetween('date_seance', [
                $request->date_debut ?? today()->startOfMonth(),
                $request->date_fin   ?? today()->endOfMonth(),
            ])
            ->orderBy('date_seance')
            ->orderBy('heure_debut')
            ->get();

        return response()->json(['success' => true, 'data' => $seances]);
    }

    public function show(string $id): JsonResponse
    {
        $seance = Seance::with([
            'cours.enseignant',
            'cours.groupe.matiere',
            'cours.salle',
            'presences.eleve',
        ])->findOrFail($id);

        $stats = [
            'total_inscrits' => $seance->cours->groupe->inscriptions()
                                       ->where('statut', 'validée')->count(),
            'presents'       => $seance->presences->whereIn('statut', ['présent','retard'])->count(),
            'absents'        => $seance->presences->where('statut', 'absent')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge($seance->toArray(), ['stats' => $stats]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cours_id'    => 'required|uuid|exists:cours,id',
            'date_seance' => 'required|date',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin'   => 'nullable|date_format:H:i',
        ]);

        $seance = Seance::create($validated);
        return response()->json(['success' => true, 'data' => $seance], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $seance->delete();
        return response()->json(['success' => true, 'message' => 'Séance supprimée']);
    }

    public function demarrer(string $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $seance->update(['statut' => 'en_cours']);
        return response()->json(['success' => true, 'message' => 'Séance démarrée']);
    }

    public function terminer(string $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $seance->update(['statut' => 'terminée']);
        return response()->json(['success' => true, 'message' => 'Séance terminée']);
    }

    public function annuler(Request $request, string $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $seance->update([
            'statut'            => 'annulée',
            'motif_annulation'  => $request->motif,
        ]);
        return response()->json(['success' => true, 'message' => 'Séance annulée']);
    }

    public function reporter(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'nouvelle_date' => 'required|date|after:today',
        ]);

        $seance = Seance::findOrFail($id);
        $seance->update([
            'statut'      => 'reportée',
            'date_seance' => $request->nouvelle_date,
        ]);

        return response()->json(['success' => true, 'message' => 'Séance reportée']);
    }
}
