<?php
namespace App\Http\Controllers\Api\V1;

use App\Exports\ElevesExport;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Eleve\StoreEleveRequest;
use App\Http\Requests\Eleve\UpdateEleveRequest;
use App\Http\Resources\EleveDetailResource;
use App\Models\Eleve;
use App\Services\{EleveDossierService, EleveSuiviService};
use Illuminate\Http\{Request, JsonResponse};
use Maatwebsite\Excel\Facades\Excel;

class EleveController extends BaseApiController
{
    protected string $model      = Eleve::class;
    protected array  $with       = ['wilaya', 'parents'];
    protected array  $searchable = ['nom', 'prenom', 'numero_inscription'];
    protected array  $filterable = ['statut', 'niveau_scolaire', 'sexe', 'wilaya_id'];
    protected array  $sortable   = ['nom', 'prenom', 'created_at', 'niveau_scolaire'];
    protected int    $perPage    = 20;

    /** Un parent/enseignant ne liste que les élèves de son périmètre. */
    protected ?string $colonnePerimetreEleve = 'id';

    public function __construct(
        private EleveDossierService $dossier,
        private EleveSuiviService $suivi,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/eleves",
     *     summary="Liste paginée des élèves",
     *     tags={"Eleves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="per_page",       in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="statut",         in="query", required=false, @OA\Schema(type="string",  enum={"actif","inactif","suspendu"})),
     *     @OA\Parameter(name="niveau_scolaire",in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search",         in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des élèves",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data",  type="array", @OA\Items(ref="#/components/schemas/Eleve")),
     *                 @OA\Property(property="meta",  ref="#/components/schemas/PaginationMeta"),
     *                 @OA\Property(property="stats", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->per_page ?? $this->perPage;

        $query = $this->indexQuery($request)
            ->with([
                'wilaya:id,nom_fr',
                'commune:id,nom_fr',
                'parents:id,nom,prenom,telephone_1,email',
                'diagnosticEleve:id,eleve_id,score_risque,niveau_global',
                'inscriptions' => fn($q) => $q->where('statut', 'validée')
                    ->with('groupe:id,nom,matiere_id')
                    ->select('id', 'eleve_id', 'groupe_id', 'statut'),
            ])
            ->withCount([
                'inscriptions as nb_inscriptions' => fn($q) => $q->where('statut', 'validée'),
                'presences as nb_presences',
                'presences as nb_absences' => fn($q) => $q->where('statut', 'absent'),
            ]);

        if (!in_array($request->sort ?? 'created_at', $this->sortable)) {
            $query->orderBy('created_at', 'desc');
        }

        $cursor = $query->cursorPaginate($perPage);
        $total  = $query->toBase()->count();

        // Les statistiques globales n'ont de sens que pour les rôles ayant
        // une vision complète du tenant ; sinon elles fuiteraient des
        // effectifs hors périmètre.
        $perimetre = app(\App\Services\PerimetreAccesService::class);

        $stats = !$perimetre->aVisionGlobale(auth('api')->user()) ? null : cache()->remember(
            "eleves_stats_" . config('tenant.current_id'),
            300,
            fn() => [
                'total'    => Eleve::count(),
                'actifs'   => Eleve::where('statut', 'actif')->count(),
                'nouveaux' => Eleve::whereMonth('created_at', now()->month)->count(),
            ]
        );

        $itemCount = $cursor->count();

        return response()->json([
            'success' => true,
            'message' => 'Élèves récupérés',
            'data'    => $cursor->items(),
            'meta'    => [
                'total'        => $total,
                'per_page'     => (int) $perPage,
                'current_page' => 1,
                'last_page'    => (int) ceil($total / $perPage),
                'has_more'     => $cursor->hasMorePages(),
                'stats'        => $stats,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/eleves",
     *     summary="Créer un élève",
     *     tags={"Eleves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EleveInput")),
     *     @OA\Response(response=201, description="Élève créé",      @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=422, description="Données invalides",@OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StoreEleveRequest $request): JsonResponse
    {
        $eleve = $this->dossier->store($request)['eleve'];

        return $this->created(
            $eleve,
            "Élève {$eleve->nom} {$eleve->prenom} inscrit avec succès"
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/eleves/{id}",
     *     summary="Détail d'un élève",
     *     tags={"Eleves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Détail élève",   @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=404, description="Non trouvé",     @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->show($eleve);

        return $this->success([
            'eleve'       => new EleveDetailResource($result['eleve']),
            'statistiques'=> $result['statistiques'],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/eleves/{id}",
     *     summary="Mettre à jour un élève",
     *     tags={"Eleves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EleveInput")),
     *     @OA\Response(response=200, description="Élève mis à jour", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=404, description="Non trouvé",       @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdateEleveRequest $request, string $id): JsonResponse
    {
        $eleve  = Eleve::findOrFail($id);
        $result = $this->dossier->update($eleve, $request->validated());

        return $this->success(
            $result['eleve'],
            'Élève mis à jour avec succès'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/eleves/{id}",
     *     summary="Supprimer un élève (soft delete)",
     *     tags={"Eleves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Supprimé",    @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=404, description="Non trouvé",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $eleve  = Eleve::findOrFail($id);
        $result = $this->dossier->destroy($eleve);

        if (isset($result['error'])) {
            return $this->error($result['error'], 'HAS_UNPAID_INVOICES', 422);
        }

        return $this->success(null, $result['message']);
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $eleve  = Eleve::findOrFail($id);
        $result = $this->dossier->uploadPhoto($eleve, $request);

        return $this->success(['photo_url' => $result['photo_url']], 'Photo mise à jour');
    }

    public function notes(Request $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->notes($eleve, $request);

        return $this->success([
            'notes'            => $result['notes'],
            'moyenne_generale' => $result['moyenne_generale'],
            'taux_presence'    => $result['taux_presence'],
        ]);
    }

    public function presences(Request $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->presences($eleve, $request);

        return $this->paginatedResponse($result['paginator'], 'Présences récupérées', ['stats' => $result['stats']]);
    }

    public function paiements(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->paiements($eleve);

        return $this->success([
            'factures'  => $result['factures'],
            'financier' => $result['financier'],
        ]);
    }

    public function bulletins(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->bulletins($eleve);

        return $this->success($result['bulletins']);
    }

    public function statistiques(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        if ($reponse = $this->verifierPerimetreEleve($eleve->id)) {
            return $reponse;
        }

        $result = $this->suivi->statistiques($eleve);

        return $this->success($result['eleve']);
    }

    public function inscrire(Request $request, string $id): JsonResponse
    {
        $eleve  = Eleve::findOrFail($id);
        $result = $this->dossier->inscrire($eleve, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], 409);
        }

        return $this->created($result['inscription'], $result['message']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        // Stub — à implémenter avec maatwebsite/excel
        $request->file('fichier')->store('imports');
        return $this->success(null, 'Import en cours de traitement');
    }

    public function export(Request $request)
    {
        $eleves = Eleve::with(['wilaya', 'parents'])->get();
        return $this->success($eleves);
    }

    public function exportExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new ElevesExport(config('tenant.current_id')),
            'eleves.xlsx'
        );
    }
}
