<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParentEleve;
use Illuminate\Http\{Request, JsonResponse};

class ParentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parents = ParentEleve::with('eleves')
            ->when($request->search, fn($q) => $q->where(function($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('prenom', 'like', "%{$request->search}%");
            }))
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $parents]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'relation'         => 'required|string|max:50',
            'telephone'        => 'required|string|max:20',
            'email'            => 'nullable|email',
            'profession'       => 'nullable|string|max:100',
            'eleve_ids'        => 'sometimes|array',
            'eleve_ids.*'      => 'exists:eleves,id',
        ]);

        $parent = ParentEleve::create($validated);

        if (!empty($validated['eleve_ids'])) {
            $parent->eleves()->sync($validated['eleve_ids']);
        }

        return response()->json(['success' => true, 'message' => 'Parent créé', 'data' => $parent->load('eleves')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $parent = ParentEleve::with('eleves')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $parent]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $parent = ParentEleve::findOrFail($id);
        $validated = $request->validate([
            'nom'        => 'sometimes|string|max:100',
            'prenom'     => 'sometimes|string|max:100',
            'relation'   => 'sometimes|string|max:50',
            'telephone'  => 'sometimes|string|max:20',
            'email'      => 'nullable|email',
            'profession' => 'nullable|string|max:100',
        ]);
        $parent->update($validated);

        if ($request->has('eleve_ids')) {
            $parent->eleves()->sync($request->eleve_ids);
        }

        return response()->json(['success' => true, 'message' => 'Parent mis à jour', 'data' => $parent->fresh('eleves')]);
    }

    public function destroy(string $id): JsonResponse
    {
        ParentEleve::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Parent supprimé']);
    }
}
