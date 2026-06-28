<?php
namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\OffrePublique;
use App\Models\Enseignant;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;

class OffreController extends Controller
{
    public function recherche(Request $request): JsonResponse
    {
        $query = OffrePublique::with(['enseignant.user', 'matiere', 'wilaya'])
            ->where('statut', 'active');

        if ($request->filled('wilaya_id')) {
            $query->where('wilaya_id', $request->wilaya_id);
        }
        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }
        if ($request->filled('niveau')) {
            $query->where('niveau', $request->niveau);
        }
        if ($request->filled('tarif_min')) {
            $query->where('tarif_seance', '>=', $request->tarif_min);
        }
        if ($request->filled('tarif_max')) {
            $query->where('tarif_seance', '<=', $request->tarif_max);
        }
        if ($request->filled('type_cours')) {
            $query->where('type_cours', $request->type_cours);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('description', 'like', "%{$q}%")
                    ->orWhere('niveau', 'like', "%{$q}%");
            });
        }

        $offres = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 12);

        return response()->json([
            'success' => true,
            'data'    => $offres->items(),
            'meta'    => [
                'current_page' => $offres->currentPage(),
                'last_page'    => $offres->lastPage(),
                'total'        => $offres->total(),
                'per_page'     => $offres->perPage(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $offre = OffrePublique::with([
            'enseignant.user',
            'matiere',
            'wilaya',
        ])->withAvg('reservations.avis', 'note')
            ->withCount('reservations as reservations_count')
            ->findOrFail($id);

        $noteMoyenne = Avis::whereHas('reservation', fn($q) => $q->where('offre_id', $offre->id))
            ->avg('note');

        return response()->json([
            'success' => true,
            'data'    => [
                'offre'        => $offre,
                'note_moyenne' => $noteMoyenne ? round($noteMoyenne, 1) : null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_offre'    => 'required|in:enseignant,centre',
            'matiere_id'    => 'required|uuid|exists:matieres,id',
            'niveau'        => 'required|string|max:50',
            'tarif_seance'  => 'required|numeric|min:0',
            'tarif_mensuel' => 'nullable|numeric|min:0',
            'type_cours'    => 'required|in:presentiel,en_ligne,les_deux',
            'wilaya_id'     => 'nullable|integer|exists:wilayas,id',
            'adresse'       => 'nullable|string',
            'capacite_max'  => 'nullable|integer|min:1',
            'places_restantes' => 'nullable|integer|min:0',
            'description'   => 'nullable|string',
        ]);

        $user = Auth::user();

        if ($validated['type_offre'] === 'enseignant') {
            $enseignant = Enseignant::where('user_id', $user->id)->first();
            if (!$enseignant) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'NO_ENSEIGNANT', 'message' => 'Vous devez être un enseignant pour créer une offre de type enseignant'],
                ], 403);
            }
            $validated['enseignant_id'] = $enseignant->id;
        }

        $validated['places_restantes'] ??= $validated['capacite_max'] ?? 1;

        $offre = OffrePublique::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Offre créée',
            'data'    => $offre->fresh(),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $offre = OffrePublique::findOrFail($id);

        $this->authorizeOwnership($offre);

        $validated = $request->validate([
            'matiere_id'    => 'sometimes|uuid|exists:matieres,id',
            'niveau'        => 'sometimes|string|max:50',
            'tarif_seance'  => 'sometimes|numeric|min:0',
            'tarif_mensuel' => 'nullable|numeric|min:0',
            'type_cours'    => 'sometimes|in:presentiel,en_ligne,les_deux',
            'wilaya_id'     => 'nullable|integer|exists:wilayas,id',
            'adresse'       => 'nullable|string',
            'capacite_max'  => 'nullable|integer|min:1',
            'places_restantes' => 'nullable|integer|min:0',
            'description'   => 'nullable|string',
            'statut'        => 'sometimes|in:active,inactive,archivee',
        ]);

        $offre->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Offre mise à jour',
            'data'    => $offre->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $offre = OffrePublique::findOrFail($id);

        $this->authorizeOwnership($offre);

        $offre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Offre supprimée',
        ]);
    }

    public function mesOffres(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = OffrePublique::with(['matiere', 'wilaya']);

        $enseignant = Enseignant::where('user_id', $user->id)->first();
        if ($enseignant) {
            $query->where('enseignant_id', $enseignant->id);
        } else {
            $query->whereNull('enseignant_id');
        }

        $offres = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $offres,
        ]);
    }

    private function authorizeOwnership(OffrePublique $offre): void
    {
        $user = Auth::user();

        if ($offre->enseignant_id) {
            $enseignant = Enseignant::where('user_id', $user->id)->first();
            if (!$enseignant || $enseignant->id !== $offre->enseignant_id) {
                abort(403, 'Vous n\'êtes pas le propriétaire de cette offre');
            }
        }
    }
}
