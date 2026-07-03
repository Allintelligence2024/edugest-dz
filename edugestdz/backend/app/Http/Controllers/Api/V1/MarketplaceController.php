<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\ReservationMarketplace;
use App\Models\AvisMarketplace;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(private MarketplaceService $service) {}

    public function recherche(Request $request): JsonResponse
    {
        $filtres  = $request->only(['wilaya', 'matiere', 'niveau', 'tarif_max', 'essai_gratuit', 'verifie']);
        $resultats = $this->service->rechercher($filtres);

        return response()->json([
            'success' => true,
            'data'    => [
                'centres' => $resultats,
                'total'   => $resultats->count(),
                'filtres' => $filtres,
            ],
            'message' => 'Résultats de recherche',
        ]);
    }

    public function profilPublic(string $tenantId): JsonResponse
    {
        $profil = ProfilMarketplace::where('tenant_id', $tenantId)
            ->where('visible', true)
            ->with([
                'offres' => fn($q) => $q->active(),
                'avis'   => fn($q) => $q->with('parent:id,prenom,nom')->limit(10),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $profil,
            'message' => 'Profil centre',
        ]);
    }

    public function featured(): JsonResponse
    {
        $centres = ProfilMarketplace::visible()
            ->where('verifie', true)
            ->orderByDesc('note_moyenne')
            ->orderByDesc('nb_avis')
            ->limit(6)
            ->get(['id','tenant_id','nom_etablissement','wilaya','logo_url',
                   'note_moyenne','nb_avis','tarif_heure_min','tarif_heure_max',
                   'matieres_enseignees','accepte_essai_gratuit']);

        return response()->json(['success' => true, 'data' => $centres]);
    }

    public function monProfil(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $profil   = ProfilMarketplace::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['nom_etablissement' => 'Mon centre', 'wilaya' => 'Alger', 'adresse' => '']
        );

        return response()->json(['success' => true, 'data' => $profil]);
    }

    public function updateProfil(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_etablissement'   => 'sometimes|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'adresse'             => 'sometimes|string|max:500',
            'wilaya'              => 'sometimes|string|max:60',
            'commune'             => 'sometimes|nullable|string|max:60',
            'telephone'           => 'sometimes|nullable|string|max:20',
            'email'               => 'sometimes|nullable|email',
            'site_web'            => 'sometimes|nullable|url',
            'tarif_heure_min'     => 'sometimes|nullable|numeric|min:0',
            'tarif_heure_max'     => 'sometimes|nullable|numeric|min:0',
            'matieres_enseignees' => 'sometimes|array',
            'niveaux_couverts'    => 'sometimes|array',
            'horaires'            => 'sometimes|array',
            'accepte_essai_gratuit' => 'sometimes|boolean',
            'visible'             => 'sometimes|boolean',
        ]);

        $profil = ProfilMarketplace::where('tenant_id', config('tenant.current_id'))
            ->firstOrFail();
        $profil->update($validated);

        return response()->json(['success' => true, 'data' => $profil, 'message' => 'Profil mis à jour']);
    }

    public function indexOffres(Request $request): JsonResponse
    {
        $offres = OffreCours::where('tenant_id', config('tenant.current_id'))
            ->when($request->filled('matiere'), fn($q) => $q->where('matiere', $request->matiere))
            ->when($request->filled('active'),  fn($q) => $q->where('active', (bool) $request->active))
            ->withCount('reservations')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $offres]);
    }

    public function storeOffre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'         => 'required|string|max:200',
            'description'   => 'nullable|string',
            'matiere'       => 'required|string|max:100',
            'niveaux'       => 'array',
            'type'          => 'in:individuel,groupe,en_ligne',
            'tarif_heure'   => 'required|numeric|min:0',
            'duree_seance'  => 'integer|min:30|max:240',
            'nb_places_max' => 'nullable|integer|min:1',
            'essai_gratuit' => 'boolean',
        ]);

        $offre = OffreCours::create([
            ...$validated,
            'tenant_id' => config('tenant.current_id'),
            'active'    => true,
        ]);

        return response()->json(['success' => true, 'data' => $offre, 'message' => 'Offre créée'], 201);
    }

    public function indexReservationsCentre(Request $request): JsonResponse
    {
        $reservations = ReservationMarketplace::where('tenant_id', config('tenant.current_id'))
            ->when($request->filled('statut'), fn($q) => $q->where('statut', $request->statut))
            ->with(['offre:id,titre,matiere', 'eleve:id,nom,prenom', 'parent:id,nom,prenom,email'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    public function confirmerReservation(Request $request, string $id): JsonResponse
    {
        $reservation = ReservationMarketplace::where('tenant_id', config('tenant.current_id'))
            ->where('statut', 'en_attente')
            ->findOrFail($id);

        $updated = $this->service->confirmerReservation(
            $reservation,
            $request->input('reponse')
        );

        return response()->json(['success' => true, 'data' => $updated, 'message' => 'Réservation confirmée']);
    }

    public function annulerReservationCentre(Request $request, string $id): JsonResponse
    {
        $reservation = ReservationMarketplace::findOrFail($id);

        $par = config('tenant.current_id') === $reservation->tenant_id ? 'centre' : 'parent';

        try {
            $updated = $this->service->annulerReservation($reservation, $par, $request->input('motif'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $updated, 'message' => 'Réservation annulée']);
    }

    public function reserver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offre_id'       => 'required|uuid|exists:offres_cours,id',
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'date_souhaitee' => 'required|date|after:now',
            'type'           => 'in:essai,cours_unique,cours_regulier',
            'message_parent' => 'nullable|string|max:500',
            'duree_minutes'  => 'nullable|integer|min:30|max:240',
        ]);

        $reservation = $this->service->creerReservation([
            ...$validated,
            'parent_id' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $reservation,
            'message' => 'Réservation soumise — en attente de confirmation du centre',
        ], 201);
    }

    public function mesReservations(Request $request): JsonResponse
    {
        $reservations = ReservationMarketplace::where('parent_id', auth('api')->id())
            ->with(['offre:id,titre,matiere,tarif_heure', 'eleve:id,nom,prenom'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    public function soumettreAvis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'      => 'required|uuid',
            'reservation_id' => 'nullable|uuid|exists:reservations_marketplace,id',
            'note'           => 'required|integer|min:1|max:5',
            'titre'          => 'nullable|string|max:150',
            'commentaire'    => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['reservation_id'])) {
            $existe = AvisMarketplace::where('reservation_id', $validated['reservation_id'])->exists();
            if ($existe) {
                return response()->json(['success' => false, 'message' => 'Vous avez déjà noté cette réservation.'], 422);
            }
        }

        $avis = AvisMarketplace::create([
            ...$validated,
            'parent_id'  => auth('api')->id(),
            'publie_le'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $avis, 'message' => 'Avis soumis, merci !'], 201);
    }

    public function toggleFavori(string $tenantId): JsonResponse
    {
        $parentId = auth('api')->id();

        $favori = \App\Models\FavoriMarketplace::where('parent_id', $parentId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($favori) {
            $favori->delete();
            $action = 'retiré';
        } else {
            \App\Models\FavoriMarketplace::create(['parent_id' => $parentId, 'tenant_id' => $tenantId]);
            $action = 'ajouté';
        }

        return response()->json(['success' => true, 'message' => "Centre {$action} des favoris"]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->getStats()]);
    }
}
