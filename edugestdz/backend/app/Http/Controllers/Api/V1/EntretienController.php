<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use App\Services\EntretienPreventifService;
use App\Services\EntretienService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntretienController extends BaseApiController
{
    public function __construct(
        private readonly EntretienService $entretiens,
        private readonly EntretienPreventifService $preventifs,
    ) {}

    // ═══════════════════════════════════════════
    // LOCAUX
    // ═══════════════════════════════════════════

    public function indexLocaux(Request $request): JsonResponse
    {
        $locaux = LocalBatiment::actifs()
            ->withCount(['interventionsOuvertes as tickets_ouverts'])
            ->orderBy('nom')
            ->get()
            ->map(fn($l) => array_merge($l->toArray(), [
                'type_label'  => $l->type_label,
                'etat_label'  => $l->etat_label,
            ]));

        return $this->success([
            'locaux' => $locaux,
            'stats'  => [
                'total'    => $locaux->count(),
                'critique' => $locaux->where('etat_general', 'critique')->count(),
                'mauvais'  => $locaux->where('etat_general', 'mauvais')->count(),
            ],
        ], 'Locaux récupérés');
    }

    public function storeLocal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:100',
            'type'         => 'required|in:salle_cours,bureau,couloir,cour,sanitaires,cantine,gymnase,entree,parking,laboratoire,bibliotheque,autre',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'etat_general' => 'nullable|in:bon,moyen,mauvais,critique',
            'note'         => 'nullable|string|max:500',
        ]);

        $local = LocalBatiment::create($validated);
        return $this->created(
            array_merge($local->toArray(), ['type_label' => $local->type_label]),
            "Local '{$local->nom}' créé"
        );
    }

    public function updateLocal(Request $request, string $id): JsonResponse
    {
        $local     = LocalBatiment::findOrFail($id);
        $validated = $request->validate([
            'nom'          => 'sometimes|string|max:100',
            'etat_general' => 'sometimes|in:bon,moyen,mauvais,critique',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'note'         => 'nullable|string|max:500',
        ]);

        $local->update($validated);
        return $this->success(
            array_merge($local->fresh()->toArray(), ['etat_label' => $local->fresh()->etat_label]),
            'Local mis à jour'
        );
    }

    public function destroyLocal(string $id): JsonResponse
    {
        $local = LocalBatiment::findOrFail($id);
        if ($local->interventionsOuvertes()->exists()) {
            return $this->error(
                'Des interventions sont en cours sur ce local',
                'HAS_INTERVENTIONS', 422
            );
        }
        $nom = $local->nom;
        $local->delete();
        return $this->success(null, "Local '{$nom}' supprimé");
    }

    // ═══════════════════════════════════════════
    // PRESTATAIRES
    // ═══════════════════════════════════════════

    public function indexPrestataires(): JsonResponse
    {
        $prestataires = PrestatireEntretien::where('actif', true)
            ->withCount('interventions')
            ->orderBy('nom')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), [
                'specialite_label' => $p->specialite_label,
            ]));

        return $this->success($prestataires, 'Prestataires récupérés');
    }

    public function storePrestataire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:150',
            'specialite'=> 'required|in:plomberie,electricite,peinture,climatisation,menuiserie,maconnerie,nettoyage,informatique,jardinage,securite,general,autre',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'adresse'   => 'nullable|string|max:200',
            'note'      => 'nullable|string|max:500',
        ]);

        $prestataire = PrestatireEntretien::create($validated);
        return $this->created($prestataire, "Prestataire '{$prestataire->nom}' ajouté");
    }

    public function updatePrestataire(Request $request, string $id): JsonResponse
    {
        $prestataire = PrestatireEntretien::findOrFail($id);
        $validated   = $request->validate([
            'nom'       => 'sometimes|string|max:150',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'actif'     => 'sometimes|boolean',
            'note'      => 'nullable|string|max:500',
        ]);
        $prestataire->update($validated);
        return $this->success($prestataire->fresh(), 'Prestataire mis à jour');
    }

    // ═══════════════════════════════════════════
    // INTERVENTIONS (TICKETS)
    // ═══════════════════════════════════════════

    /**
     * @OA\Get(
     *     path="/api/v1/entretien/interventions",
     *     summary="Liste des interventions d'entretien",
     *     tags={"Entretien"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"signale","en_cours","en_attente","resolu","annule"})),
     *     @OA\Parameter(name="priorite", in="query", @OA\Schema(type="string", enum={"urgente","haute","normale","basse"})),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Interventions paginées", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function indexInterventions(Request $request): JsonResponse
    {
        $result = $this->entretiens->index($request);

        return $this->paginatedResponse($result['paginator'], 'Interventions récupérées', ['stats' => $result['stats']]);
    }

    public function showIntervention(string $id): JsonResponse
    {
        $intervention = InterventionEntretien::with([
            'local:id,nom,type,etage',
            'prestataire:id,nom,specialite,telephone',
            'signalePar:id,nom,prenom',
            'depense',
        ])->findOrFail($id);

        return $this->success([
            'intervention'   => $intervention,
            'priorite_label' => $intervention->priorite_label,
            'statut_label'   => $intervention->statut_label,
            'duree_jours'    => $intervention->duree_jours,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/entretien/interventions",
     *     summary="Signaler une intervention d'entretien",
     *     tags={"Entretien"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"titre","type","priorite"},
     *             @OA\Property(property="titre",         type="string"),
     *             @OA\Property(property="description",   type="string", nullable=true),
     *             @OA\Property(property="type",          type="string", enum={"panne","degradation","entretien_preventif","renovation","nettoyage","inspection"}),
     *             @OA\Property(property="priorite",      type="string", enum={"urgente","haute","normale","basse"}),
     *             @OA\Property(property="local_id",      type="string", format="uuid", nullable=true),
     *             @OA\Property(property="prestataire_id",type="string", format="uuid", nullable=true),
     *             @OA\Property(property="cout_estime",   type="number", format="float", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Intervention créée", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function signalerIntervention(Request $request): JsonResponse
    {
        $result = $this->entretiens->signalerIntervention($request->all());

        return $this->created([
            'intervention'   => $result['intervention'],
            'priorite_label' => $result['priorite_label'],
        ], $result['message']);
    }

    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $result = $this->entretiens->changerStatut($id, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success($result['intervention'], $result['message']);
    }

    public function resoudreIntervention(Request $request, string $id): JsonResponse
    {
        $result = $this->entretiens->resoudreIntervention($id, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success([
            'intervention' => $result['intervention'],
            'depense_creee'=> $result['depense_creee'],
        ], $result['message']);
    }

    // ═══════════════════════════════════════════
    // ENTRETIENS PRÉVENTIFS
    // ═══════════════════════════════════════════

    public function indexPreventif(): JsonResponse
    {
        $result = $this->preventifs->index();

        return $this->success([
            'entretiens' => $result['entretiens'],
            'alertes_30j'=> $result['alertes_30j'],
        ], 'Entretiens préventifs récupérés');
    }

    public function planifierPreventif(Request $request): JsonResponse
    {
        $result = $this->preventifs->planifierPreventif($request->all());

        return $this->created($result['entretien'], $result['message']);
    }

    public function realiserPreventif(Request $request, string $id): JsonResponse
    {
        $result = $this->preventifs->realiserPreventif($id, $request->all());

        return $this->success([
            'entretien'        => $result['entretien'],
            'prochaine_echeance'=> $result['prochaine_echeance'],
        ], $result['message']);
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today = today();

        $stats = [
            'tickets_ouverts'   => InterventionEntretien::ouverts()->count(),
            'tickets_urgents'   => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'resolus_ce_mois'   => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->count(),
            'cout_mois'         => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->sum('cout_reel'),
            'locaux_critique'   => LocalBatiment::actifs()->where('etat_general', 'critique')->count(),
            'preventifs_retard' => EntretienPreventif::where('actif', true)
                ->where('prochaine_echeance', '<', $today)->count(),
            'preventifs_30j'    => EntretienPreventif::where('actif', true)
                ->whereBetween('prochaine_echeance', [$today, $today->copy()->addDays(30)])->count(),
        ];

        $derniersTickets = InterventionEntretien::ouverts()
            ->with(['local:id,nom', 'prestataire:id,nom'])
            ->orderByRaw("CASE priorite WHEN 'urgente' THEN 1 WHEN 'haute' THEN 2 ELSE 3 END")
            ->limit(5)->get();

        return $this->success([
            'stats'          => $stats,
            'derniers_tickets'=> $derniersTickets,
        ], 'Tableau de bord entretien bâtiment');
    }
}
