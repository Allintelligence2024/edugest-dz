<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\{Request, JsonResponse};

class MatiereController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matieres = Matiere::withCount('groupes')
            ->when($request->search, fn($q) => $q->where('nom_fr', 'like', "%{$request->search}%")
                ->orWhere('nom_ar', 'like', "%{$request->search}%"))
            ->orderBy('nom_fr')
            ->paginate($request->per_page ?? 50);

        return response()->json(['success' => true, 'data' => $matieres]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_fr'   => 'required|string|max:100',
            'nom_ar'   => 'nullable|string|max:100',
            'code'     => 'nullable|string|max:20|unique:matieres,code',
            'couleur'  => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        $matiere = Matiere::create($validated);
        return response()->json(['success' => true, 'message' => 'Matière créée', 'data' => $matiere], 201);
    }

    public function show(string $id): JsonResponse
    {
        $matiere = Matiere::with('groupes')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $matiere]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $matiere = Matiere::findOrFail($id);
        $validated = $request->validate([
            'nom_fr'   => 'sometimes|string|max:100',
            'nom_ar'   => 'nullable|string|max:100',
            'couleur'  => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);
        $matiere->update($validated);
        return response()->json(['success' => true, 'message' => 'Matière mise à jour', 'data' => $matiere->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        Matiere::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Matière supprimée']);
    }
}
