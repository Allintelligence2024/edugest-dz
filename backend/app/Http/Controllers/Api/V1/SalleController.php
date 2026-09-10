<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salle;
use Illuminate\Http\{Request, JsonResponse};

class SalleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $salles = Salle::withCount('cours')
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) => $q->where('nom', 'like', "%{$request->search}%"))
            ->orderBy('nom')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $salles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:100',
            'capacite'     => 'required|integer|min:1',
            'equipements'  => 'nullable|array',
            'description'  => 'nullable|string',
        ]);

        $salle = Salle::create($validated);
        return response()->json(['success' => true, 'message' => 'Salle créée', 'data' => $salle], 201);
    }

    public function show(string $id): JsonResponse
    {
        $salle = Salle::with('cours.groupe.matiere')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $salle]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $salle = Salle::findOrFail($id);
        $validated = $request->validate([
            'nom'          => 'sometimes|string|max:100',
            'capacite'     => 'sometimes|integer|min:1',
            'equipements'  => 'nullable|array',
            'statut'       => 'sometimes|in:disponible,occupée,maintenance',
        ]);
        $salle->update($validated);
        return response()->json(['success' => true, 'message' => 'Salle mise à jour', 'data' => $salle->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        Salle::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Salle supprimée']);
    }

    public function disponibilites(string $id, Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date']);
        $salle = Salle::findOrFail($id);
        $seances = $salle->cours()->with('seances')
            ->whereHas('seances', fn($q) => $q->where('date_seance', $request->date))
            ->get()
            ->pluck('seances')
            ->flatten()
            ->map(fn($s) => ['debut' => $s->heure_debut, 'fin' => $s->heure_fin, 'statut' => $s->statut]);

        return response()->json(['success' => true, 'data' => $seances]);
    }
}
