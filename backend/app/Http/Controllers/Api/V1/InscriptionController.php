<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use Illuminate\Http\{Request, JsonResponse};

class InscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inscriptions = Inscription::with(['eleve', 'groupe.matiere'])
            ->when($request->groupe_id, fn($q) => $q->where('groupe_id', $request->groupe_id))
            ->when($request->eleve_id, fn($q) => $q->where('eleve_id', $request->eleve_id))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $inscriptions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'  => 'required|uuid|exists:eleves,id',
            'groupe_id' => 'required|uuid|exists:groupes,id',
            'date_inscription' => 'sometimes|date',
            'statut'    => 'sometimes|in:active,inactive,suspendue',
        ]);

        $inscription = Inscription::create($validated);

        return response()->json(['success' => true, 'message' => 'Inscription créée', 'data' => $inscription->load(['eleve', 'groupe'])], 201);
    }

    public function show(string $id): JsonResponse
    {
        $inscription = Inscription::with(['eleve', 'groupe.matiere', 'notes.evaluation', 'presences'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $inscription]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $inscription = Inscription::findOrFail($id);
        $validated = $request->validate([
            'statut'         => 'sometimes|in:active,inactive,suspendue',
            'date_inscription' => 'sometimes|date',
        ]);
        $inscription->update($validated);
        return response()->json(['success' => true, 'message' => 'Inscription mise à jour', 'data' => $inscription->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        Inscription::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Inscription supprimée']);
    }
}
