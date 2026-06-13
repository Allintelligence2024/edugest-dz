<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Services\PlanningService;
use Illuminate\Http\{Request, JsonResponse};

class EnseignantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enseignants = Enseignant::with(['wilaya', 'matieres'])
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) => $q->where(function($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('prenom', 'like', "%{$request->search}%");
            }))
            ->when($request->matiere_id, fn($q) => $q->whereHas('matieres', fn($q) => $q->where('matiere_id', $request->matiere_id)))
            ->orderBy('nom')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $enseignants]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'telephone'        => 'required|string|max:20',
            'email'            => 'nullable|email|unique:enseignants,email',
            'specialite'       => 'required|string|max:100',
            'diplome'          => 'nullable|string|max:100',
            'tarif_horaire'    => 'nullable|numeric|min:0',
            'wilaya_id'        => 'nullable|exists:wilayas,id',
            'commune_id'       => 'nullable|exists:communes,id',
            'adresse'          => 'nullable|string|max:255',
            'date_embauche'    => 'sometimes|date',
            'type_contrat'     => 'sometimes|in:cdi,cdd,freelance',
            'notes'            => 'nullable|string',
            'matiere_ids'      => 'sometimes|array',
            'matiere_ids.*'    => 'exists:matieres,id',
        ]);

        $enseignant = Enseignant::create($validated);

        if (!empty($validated['matiere_ids'])) {
            $enseignant->matieres()->sync($validated['matiere_ids']);
        }

        return response()->json(['success' => true, 'message' => 'Enseignant créé', 'data' => $enseignant->load('matieres')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $enseignant = Enseignant::with(['wilaya', 'commune', 'matieres', 'contrats', 'cours.groupe'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $enseignant]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);
        $validated = $request->validate([
            'nom'            => 'sometimes|string|max:100',
            'prenom'         => 'sometimes|string|max:100',
            'telephone'      => 'sometimes|string|max:20',
            'email'          => 'nullable|email|unique:enseignants,email,' . $id,
            'tarif_horaire'  => 'nullable|numeric|min:0',
            'statut'         => 'sometimes|in:actif,inactif,suspendu',
            'notes'          => 'nullable|string',
        ]);
        $enseignant->update($validated);

        if ($request->has('matiere_ids')) {
            $enseignant->matieres()->sync($request->matiere_ids);
        }

        return response()->json(['success' => true, 'message' => 'Enseignant mis à jour', 'data' => $enseignant->fresh('matieres')]);
    }

    public function destroy(string $id): JsonResponse
    {
        Enseignant::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Enseignant supprimé']);
    }

    public function planning(string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);
        $cours = $enseignant->cours()->with('groupe.matiere', 'salle')->orderBy('jour_semaine')->orderBy('heure_debut')->get();
        return response()->json(['success' => true, 'data' => $cours]);
    }

    public function statistiques(string $id): JsonResponse
    {
        $ens = Enseignant::withCount(['cours', 'seances as seances_terminees' => fn($q) => $q->where('statut', 'terminée')])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $ens]);
    }

    public function setDisponibilites(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'disponibilites' => 'required|array',
            'disponibilites.*.jour_semaine' => 'required|integer|between:0,6',
            'disponibilites.*.heure_debut'  => 'required|date_format:H:i',
            'disponibilites.*.heure_fin'    => 'required|date_format:H:i',
        ]);

        $enseignant = Enseignant::findOrFail($id);
        $enseignant->update(['disponibilites' => $request->disponibilites]);

        return response()->json(['success' => true, 'message' => 'Disponibilités mises à jour']);
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:2048']);
        $enseignant = Enseignant::findOrFail($id);
        $path = $request->file('photo')->store('photos/enseignants', 'public');
        $enseignant->update(['photo_url' => $path]);
        return response()->json(['success' => true, 'message' => 'Photo uploadée', 'photo_url' => $path]);
    }
}
