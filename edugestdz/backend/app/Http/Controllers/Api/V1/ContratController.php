<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use Illuminate\Http\{Request, JsonResponse};

class ContratController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contrats = Contrat::with('enseignant')
            ->when($request->enseignant_id, fn($q) => $q->where('enseignant_id', $request->enseignant_id))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $contrats]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enseignant_id'    => 'required|uuid|exists:enseignants,id',
            'type_contrat'     => 'required|in:cdi,cdd,freelance',
            'date_debut'       => 'required|date',
            'date_fin'         => 'nullable|date|after:date_debut',
            'salaire_base'     => 'nullable|numeric|min:0',
            'tarif_horaire'    => 'nullable|numeric|min:0',
            'nb_heures_mois'   => 'nullable|integer|min:0',
            'conditions'       => 'nullable|string',
        ]);

        $contrat = Contrat::create($validated);

        return response()->json(['success' => true, 'message' => 'Contrat créé', 'data' => $contrat->load('enseignant')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $contrat = Contrat::with('enseignant')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $contrat]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);
        $validated = $request->validate([
            'type_contrat'   => 'sometimes|in:cdi,cdd,freelance',
            'date_fin'       => 'nullable|date|after:date_debut',
            'salaire_base'   => 'nullable|numeric|min:0',
            'tarif_horaire'  => 'nullable|numeric|min:0',
            'statut'         => 'sometimes|in:actif,suspendu,terminé,renouvelé',
            'conditions'     => 'nullable|string',
        ]);
        $contrat->update($validated);
        return response()->json(['success' => true, 'message' => 'Contrat mis à jour', 'data' => $contrat->fresh('enseignant')]);
    }

    public function destroy(string $id): JsonResponse
    {
        Contrat::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Contrat supprimé']);
    }
}
