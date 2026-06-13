<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use Illuminate\Http\{Request, JsonResponse};

class EleveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eleves = Eleve::with(['wilaya', 'parents'])
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) => $q->where(function($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('prenom', 'like', "%{$request->search}%")
                  ->orWhere('numero_inscription', 'like', "%{$request->search}%");
            }))
            ->when($request->niveau, fn($q) => $q->where('niveau_scolaire', $request->niveau))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $eleves, 'meta' => ['total' => $eleves->total()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'nom_ar'           => 'nullable|string|max:100',
            'prenom_ar'        => 'nullable|string|max:100',
            'date_naissance'   => 'required|date',
            'lieu_naissance'   => 'required|string|max:100',
            'niveau_scolaire'  => 'required|string|max:50',
            'etablissement'    => 'nullable|string|max:200',
            'wilaya_id'        => 'nullable|exists:wilayas,id',
            'commune_id'       => 'nullable|exists:communes,id',
            'adresse'          => 'nullable|string|max:255',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:100',
            'notes'            => 'nullable|string',
        ]);

        $validated['numero_inscription'] = Eleve::generateMatricule();
        $eleve = Eleve::create($validated);

        return response()->json(['success' => true, 'message' => 'Élève créé', 'data' => $eleve->load('wilaya')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $eleve = Eleve::with(['wilaya', 'commune', 'parents', 'inscriptions.groupe.matiere'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $eleve]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'nom_ar'           => 'nullable|string|max:100',
            'prenom_ar'        => 'nullable|string|max:100',
            'date_naissance'   => 'sometimes|date',
            'niveau_scolaire'  => 'sometimes|string|max:50',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:100',
            'statut'           => 'sometimes|in:actif,inactif,suspendu',
            'notes'            => 'nullable|string',
        ]);
        $eleve->update($validated);
        return response()->json(['success' => true, 'message' => 'Élève mis à jour', 'data' => $eleve->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        Eleve::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Élève supprimé']);
    }

    public function notes(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $notes = $eleve->inscriptions()->with(['notes.evaluation.matiere', 'groupe.matiere'])->get();
        return response()->json(['success' => true, 'data' => $notes]);
    }

    public function presences(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $presences = $eleve->presences()->with('seance.cours.matiere')->orderBy('created_at', 'desc')->paginate(20);
        return response()->json(['success' => true, 'data' => $presences]);
    }

    public function paiements(string $id): JsonResponse
    {
        $eleve = Eleve::with('factures.paiements')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $eleve->factures]);
    }

    public function bulletins(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        return response()->json(['success' => true, 'data' => $eleve->bulletins()->with('groupe')->orderBy('created_at', 'desc')->get()]);
    }

    public function statistiques(string $id): JsonResponse
    {
        $eleve = Eleve::withCount(['inscriptions', 'presences as total_presences' => fn($q) => $q->where('statut', 'present')])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $eleve]);
    }

    public function inscrire(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'groupe_id'  => 'required|uuid|exists:groupes,id',
            'date_inscription' => 'sometimes|date',
        ]);
        $eleve = Eleve::findOrFail($id);
        $inscription = $eleve->inscriptions()->create([
            'groupe_id'  => $validated['groupe_id'],
            'date_inscription' => $validated['date_inscription'] ?? now(),
            'statut'     => 'active',
        ]);
        return response()->json(['success' => true, 'message' => 'Élève inscrit', 'data' => $inscription->load('groupe')], 201);
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:2048']);
        $eleve = Eleve::findOrFail($id);
        $path = $request->file('photo')->store('photos/eleves', 'public');
        $eleve->update(['photo_url' => $path]);
        return response()->json(['success' => true, 'message' => 'Photo uploadée', 'photo_url' => $path]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,xlsx']);
        return response()->json(['success' => true, 'message' => 'Import à implémenter']);
    }

    public function export(Request $request): JsonResponse
    {
        $eleves = Eleve::all();
        return response()->json(['success' => true, 'data' => $eleves]);
    }
}
