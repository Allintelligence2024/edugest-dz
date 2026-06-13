<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Groupe, Inscription};
use Illuminate\Http\{Request, JsonResponse};

class GroupeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groupes = Groupe::with(['matiere', 'niveauScolaire'])
            ->withCount('inscriptions')
            ->when($request->matiere_id, fn($q) => $q->where('matiere_id', $request->matiere_id))
            ->when($request->niveau_scolaire, fn($q) => $q->where('niveau_scolaire', $request->niveau_scolaire))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->enseignant_id, fn($q) => $q->whereHas('cours', fn($q) => $q->where('enseignant_id', $request->enseignant_id)))
            ->orderBy('nom')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $groupes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'             => 'required|string|max:100',
            'matiere_id'      => 'required|uuid|exists:matieres,id',
            'niveau_scolaire' => 'required|string|max:50',
            'capacite_max'    => 'nullable|integer|min:1',
            'description'     => 'nullable|string',
        ]);

        $groupe = Groupe::create($validated);
        return response()->json(['success' => true, 'message' => 'Groupe créé', 'data' => $groupe->load('matiere')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $groupe = Groupe::with(['matiere', 'inscriptions.eleve', 'cours.enseignant'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $groupe]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $groupe = Groupe::findOrFail($id);
        $validated = $request->validate([
            'nom'             => 'sometimes|string|max:100',
            'capacite_max'    => 'nullable|integer|min:1',
            'statut'          => 'sometimes|in:actif,inactif,archivé',
            'description'     => 'nullable|string',
        ]);
        $groupe->update($validated);
        return response()->json(['success' => true, 'message' => 'Groupe mis à jour', 'data' => $groupe->fresh('matiere')]);
    }

    public function destroy(string $id): JsonResponse
    {
        Groupe::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Groupe supprimé']);
    }

    public function eleves(string $id): JsonResponse
    {
        $groupe = Groupe::with('inscriptions.eleve')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $groupe->inscriptions]);
    }

    public function addEleve(Request $request, string $id): JsonResponse
    {
        $groupe = Groupe::findOrFail($id);
        $validated = $request->validate(['eleve_id' => 'required|uuid|exists:eleves,id']);

        $exists = Inscription::where('groupe_id', $id)->where('eleve_id', $validated['eleve_id'])->exists();
        if ($exists) {
            return response()->json(['success' => false, 'error' => ['code' => 'ALREADY_IN_GROUP', 'message' => 'Cet élève est déjà dans ce groupe']], 409);
        }

        $inscription = Inscription::create([
            'eleve_id'  => $validated['eleve_id'],
            'groupe_id' => $id,
            'statut'    => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Élève ajouté au groupe', 'data' => $inscription->load('eleve')], 201);
    }

    public function removeEleve(string $id, string $eleveId): JsonResponse
    {
        Inscription::where('groupe_id', $id)->where('eleve_id', $eleveId)->delete();
        return response()->json(['success' => true, 'message' => 'Élève retiré du groupe']);
    }
}
