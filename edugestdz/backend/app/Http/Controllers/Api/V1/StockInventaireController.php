<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ArticleStock;
use App\Models\BonCommande;
use App\Models\MouvementStock;
use App\Models\PretMateriel;
use App\Models\Tenant;
use App\Services\BonCommandeService;
use App\Services\PretMaterielService;
use App\Services\StockInventaireService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StockInventaireController extends BaseApiController
{
    public function __construct(
        private readonly StockInventaireService $stock,
        private readonly PretMaterielService $prets,
        private readonly BonCommandeService $bons,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/stock/articles",
     *     summary="Liste des articles en stock",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="categorie",     in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="en_alerte",     in="query", @OA\Schema(type="boolean", description="true = articles sous le seuil")),
     *     @OA\Parameter(name="per_page",      in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Articles paginés", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->stock->index($request);

        return $this->paginatedResponse($result['paginator'], 'Articles récupérés', ['stats' => $result['stats']]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->stock->store($request->all());

        return $this->created([
            'article'          => $result['article'],
            'qr_code'          => $result['qr_code'],
            'reference'        => $result['reference'],
            'categorie_label'  => $result['categorie_label'],
        ], "Article '{$result['article']->nom}' créé · Réf : {$result['article']->reference}");
    }

    public function show(string $id): JsonResponse
    {
        return $this->success($this->stock->show($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $result = $this->stock->update($id, $request->all());

        return $this->success($result['article'], 'Article mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $result = $this->stock->destroy($id);

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success(null, $result['success']);
    }

    public function parQrCode(string $qrCode): JsonResponse
    {
        return $this->success($this->stock->parQrCode($qrCode));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock/articles/{id}/mouvement",
     *     summary="Enregistrer un mouvement de stock (entrée / sortie / ajustement)",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","quantite"},
     *             @OA\Property(property="type",       type="string",  enum={"entree","sortie","ajustement","transfert","perte"}),
     *             @OA\Property(property="quantite",   type="integer", example=10),
     *             @OA\Property(property="motif",      type="string",  nullable=true),
     *             @OA\Property(property="reference_doc", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Mouvement enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function mouvement(Request $request, string $id): JsonResponse
    {
        $result = $this->stock->mouvement($id, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success([
            'article'       => $result['article'],
            'quantite_avant'=> $result['quantite_avant'],
            'quantite_apres'=> $result['quantite_apres'],
            'en_alerte'     => $result['en_alerte'],
        ], "Stock mis à jour : {$result['article_nom']} — {$result['quantite_avant']} → {$result['quantite_apres']} {$result['article_unite']}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $result = $this->stock->historique($request, $id);

        return $this->paginatedResponse($result['paginator'], 'Historique mouvements', [
            'article' => $result['article'],
        ]);
    }

    public function alertes(): JsonResponse
    {
        $result = $this->stock->alertes();

        return $this->success([
            'articles'   => $result['articles'],
            'nb_alertes' => $result['nb_alertes'],
        ], $result['message']);
    }

    public function indexPrets(Request $request): JsonResponse
    {
        $result = $this->prets->index($request);

        return $this->paginatedResponse($result['paginator'], 'Prêts récupérés', [
            'nb_en_retard' => $result['nb_en_retard'],
        ]);
    }

    public function creerPret(Request $request): JsonResponse
    {
        $result = $this->prets->creerPret($request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->created(null, $result['success']);
    }

    public function retourPret(Request $request, string $id): JsonResponse
    {
        $result = $this->prets->retourPret($id, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success(null, $result['success']);
    }

    public function indexBons(Request $request): JsonResponse
    {
        $result = $this->bons->index($request);

        return $this->paginatedResponse($result['paginator'], 'Bons de commande récupérés');
    }

    public function creerBon(Request $request): JsonResponse
    {
        $result = $this->bons->creerBon($request->all());

        return $this->created($result['bon'], "Bon de commande {$result['bon']->numero} créé");
    }

    public function statutBon(Request $request, string $id): JsonResponse
    {
        $result = $this->bons->statutBon($id, $request->all());

        return $this->success($result['bon'], "Bon {$result['bon']->numero} — statut : {$result['statut']}");
    }

    public function pdfBon(string $id)
    {
        $bon    = BonCommande::with('lignes.article')->findOrFail($id);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $pdf  = Pdf::loadView('pdf.bon_commande', compact('bon', 'tenant'))->setPaper('A4', 'portrait');
        $path = "bons_commande/{$bon->tenant_id}/{$bon->numero}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    public function rapportInventaire(Request $request)
    {
        $annee  = (int) ($request->annee ?? now()->year);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $articles = ArticleStock::where('actif', true)
            ->orderBy('categorie')->orderBy('nom')
            ->get();

        $parCategorie = $articles->groupBy('categorie')->map(fn($grp) => [
            'articles'     => $grp,
            'nb_articles'  => $grp->count(),
            'valeur_totale'=> $grp->sum('valeur_totale'),
            'nb_alertes'   => $grp->filter(fn($a) => $a->en_alerte)->count(),
        ]);

        $pdf = Pdf::loadView('pdf.rapport_inventaire', [
            'articles'     => $articles,
            'par_categorie'=> $parCategorie,
            'tenant'       => $tenant,
            'annee'        => $annee,
            'date_rapport' => today()->format('d/m/Y'),
            'valeur_totale'=> $articles->sum('valeur_totale'),
            'nb_total'     => $articles->count(),
            'nb_alertes'   => $articles->filter(fn($a) => $a->en_alerte)->count(),
        ])->setPaper('A4', 'portrait');

        $path = "rapports/inventaire_{$annee}_" . now()->format('Ymd') . ".pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    public function dashboard(): JsonResponse
    {
        $alertes      = ArticleStock::where('actif', true)->enAlerte()->count();
        $pretsRetard  = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())->count();
        $bonsPendants = BonCommande::whereIn('statut', ['brouillon', 'envoye'])->count();

        $valeurTotale = ArticleStock::where('actif', true)
            ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
            ->value('total') ?? 0;

        $parCategorie = ArticleStock::where('actif', true)
            ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as qte')
            ->groupBy('categorie')->get();

        $derniersMovements = MouvementStock::with('article:id,nom')
            ->orderByDesc('created_at')->limit(5)->get();

        return $this->success([
            'alertes_stock'    => $alertes,
            'prets_en_retard'  => $pretsRetard,
            'bons_pendants'    => $bonsPendants,
            'valeur_totale_da' => (float) $valeurTotale,
            'par_categorie'    => $parCategorie,
            'derniers_mouvements' => $derniersMovements,
        ], 'Tableau de bord stock & inventaire');
    }
}
