<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MarketplaceController extends Controller
{
    /**
     * Recherche de centres de cours.
     * GET /api/v1/marketplace/recherche?wilaya=31&matiere=maths&niveau=3as
     */
    public function recherche(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wilaya'   => 'nullable|integer|between:1,58',
            'matiere'  => 'nullable|string|max:100',
            'niveau'   => 'nullable|string|max:50',
            'q'        => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|between:5,50',
            'page'     => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 12;

        $query = DB::table('tenants as t')
            ->join('tenant_modules as tm', function ($join) {
                $join->on('t.id', '=', 'tm.tenant_id')
                    ->where('tm.module_key', 'marketplace')
                    ->where('tm.actif', true);
            })
            ->where('t.statut', 'actif')
            ->select([
                't.id', 't.nom_etablissement', 't.description', 't.wilaya_id as wilaya',
                't.adresse', 't.telephone', 't.email', 't.logo_url',
                't.type_etablissement',
            ]);

        if (!empty($validated['wilaya'])) {
            $query->where('t.wilaya_id', $validated['wilaya']);
        }

        if (!empty($validated['q'])) {
            $search = '%' . $validated['q'] . '%';
            $query->where(fn($q) => $q->where('t.nom_etablissement', 'ilike', $search)
                ->orWhere('t.description', 'ilike', $search));
        }

        $resultats = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $resultats->items(),
            'meta'    => [
                'current_page' => $resultats->currentPage(),
                'per_page'     => $resultats->perPage(),
                'total'        => $resultats->total(),
                'last_page'    => $resultats->lastPage(),
            ],
        ]);
    }

    /**
     * Centres mis en avant (featured).
     * GET /api/v1/marketplace/featured
     */
    public function featured(): JsonResponse
    {
        $centres = Cache::remember('marketplace_featured', 300, function () {
            return DB::table('tenants as t')
                ->join('tenant_modules as tm', function ($join) {
                    $join->on('t.id', '=', 'tm.tenant_id')
                        ->where('tm.module_key', 'marketplace')
                        ->where('tm.actif', true);
                })
                ->where('t.statut', 'actif')
                ->where('t.marketplace_featured', true)
                ->select(['t.id', 't.nom_etablissement', 't.description', 't.wilaya_id as wilaya', 't.logo_url', 't.type_etablissement'])
                ->limit(6)
                ->get();
        });

        return response()->json(['success' => true, 'data' => $centres]);
    }

    /**
     * Profil public d'un centre.
     * GET /api/v1/marketplace/centres/{tenantId}
     */
    public function profil(string $tenantId): JsonResponse
    {
        $moduleActif = TenantModule::where('tenant_id', $tenantId)
            ->where('module_key', 'marketplace')
            ->where('actif', true)
            ->exists();

        if (!$moduleActif) {
            return response()->json([
                'success' => false,
                'message' => 'Ce centre n\'est pas référencé sur la marketplace.',
            ], 404);
        }

        $centre = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('statut', 'actif')
            ->select(['id', 'nom_etablissement', 'description', 'wilaya_id as wilaya', 'adresse', 'telephone', 'email', 'logo_url', 'type_etablissement'])
            ->first();

        if (!$centre) {
            return response()->json(['success' => false, 'message' => 'Centre non trouvé.'], 404);
        }

        $offres = DB::table('offres_publiques')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'active')
            ->select(['id', 'type_offre', 'description', 'niveau', 'tarif_seance', 'places_restantes'])
            ->get();

        $stats = DB::table('avis_marketplace')
            ->where('tenant_id', $tenantId)
            ->where('visible', true)
            ->selectRaw('AVG(note) as note_moyenne, COUNT(*) as nb_avis')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'centre'       => $centre,
                'offres'       => $offres,
                'note_moyenne' => round($stats->note_moyenne ?? 0, 1),
                'nb_avis'      => $stats->nb_avis ?? 0,
            ],
        ]);
    }

    /**
     * Statistiques générales de la marketplace.
     * GET /api/v1/marketplace/stats
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('marketplace_stats', 3600, function () {
            $totalCentres = DB::table('tenant_modules')
                ->where('module_key', 'marketplace')
                ->where('actif', true)
                ->count();

            return [
                'total_centres'     => $totalCentres,
                'total_wilayas'     => DB::table('tenants')->distinct('wilaya_id')->count('wilaya_id'),
                'message'           => 'Marketplace EduGest DZ — Trouvez votre centre de cours',
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Méthodes protégées (derrière auth:api + module:marketplace)
    // ══════════════════════════════════════════════════════════════════

    public function monProfil(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $centre = DB::table('tenants')->where('id', $tenantId)->first();

        return response()->json([
            'success' => true,
            'data'    => $centre,
        ]);
    }

    public function updateProfil(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function indexOffres(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    public function storeOffre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'   => 'required|string|max:200',
            'matiere' => 'required|string|max:100',
        ]);

        return response()->json(['success' => true, 'data' => []], 201);
    }

    public function indexReservationsCentre(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    public function confirmerReservation(Request $request, string $id): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function annulerReservationCentre(Request $request, string $id): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function reserver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offre_id'       => 'required|string',
            'eleve_id'       => 'required|string',
            'date_souhaitee' => 'required|date|after:now',
        ]);

        return response()->json(['success' => true], 201);
    }

    public function mesReservations(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    public function soumettreAvis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'  => 'required|string',
            'note'       => 'required|integer|between:1,5',
            'titre'      => 'nullable|string|max:200',
            'commentaire'=> 'nullable|string|max:2000',
        ]);

        return response()->json(['success' => true], 201);
    }

    public function toggleFavori(Request $request, string $tenantId): JsonResponse
    {
        return response()->json(['success' => true, 'favori' => true]);
    }
}
