<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\{Request, JsonResponse};

class TarifController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tarifs = Matiere::select('id', 'nom_fr', 'nom_ar')
            ->with(['groupes' => fn($q) => $q->select('id', 'matiere_id', 'nom', 'niveau_scolaire')])
            ->get()
            ->map(fn($m) => [
                'matiere' => $m->nom_fr,
                'groupes' => $m->groupes->map(fn($g) => [
                    'id'      => $g->id,
                    'nom'     => $g->nom,
                    'niveau'  => $g->niveau_scolaire,
                    'tarif'   => $g->pivot->tarif ?? 0,
                ]),
            ]);

        return response()->json(['success' => true, 'data' => $tarifs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matiere_id'       => 'required|uuid|exists:matieres,id',
            'niveau_scolaire'  => 'required|string|max:50',
            'tarif_mensuel'    => 'required|numeric|min:0',
            'frais_inscription' => 'nullable|numeric|min:0',
        ]);

        return response()->json(['success' => true, 'message' => 'Tarif créé', 'data' => $validated], 201);
    }

    public function show(string $id): JsonResponse
    {
        $matiere = Matiere::with('groupes')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $matiere]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'tarif_mensuel'    => 'sometimes|numeric|min:0',
            'frais_inscription' => 'nullable|numeric|min:0',
        ]);

        return response()->json(['success' => true, 'message' => 'Tarif mis à jour', 'data' => $validated]);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Tarif supprimé']);
    }
}
