<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\StockInventaireService;
use App\Services\PretMaterielService;
use App\Services\BonCommandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockInventaireController extends BaseApiController
{
    protected $stockService;
    protected $pretService;
    protected $bonService;

    public function __construct(
        StockInventaireService $stockService,
        PretMaterielService $pretService,
        BonCommandeService $bonService
    ) {
        $this->stockService = $stockService;
        $this->pretService  = $pretService;
        $this->bonService   = $bonService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock/articles",
     *     summary="Liste des articles en stock",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string", maxLength=100)),
     *     @OA\Parameter(name="categorie", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="etat", in="query", @OA\Schema(type="string", enum={"bon","use","hors_service","en_reparation"})),
     *     @OA\Parameter(name="en_alerte", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="immobilise", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Articles paginés", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->stockService->index($request);
        return $this->paginatedResponse(
            $result['paginator'],
            'Articles récupérés',
            ['stats' => $result['stats']]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->stockService->store($request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'] ?? 'BAD_REQUEST', $result['status'] ?? 400);
        }
        $r = $result;
        return $this->created([
            'article'          => $r['article'],
            'qr_code'          => $r['qr_code'],
            'reference'        => $r['reference'],
            'categorie_label'  => $r['categorie_label'],
        ], "Article '{$r['article']->nom}' créé · Réf : {$r['reference']}");
    }

    public function show(string $id): JsonResponse
    {
        $result = $this->stockService->show($id);
        return $this->success([
            'article'        => $result['article'],
            'etat_label'     => $result['etat_label'],
            'categorie_label'=> $result['categorie_label'],
            'en_alerte'      => $result['en_alerte'],
            'valeur_totale'  => $result['valeur_totale'],
            'nb_prets_cours' => $result['nb_prets_cours'],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $result = $this->stockService->update($id, $request->all());
        return $this->success($result['article'] ?? null, 'Article mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $result = $this->stockService->destroy($id);
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        return $this->success(null, "Article '{$result['success']}'");
    }

    public function parQrCode(string $qrCode): JsonResponse
    {
        $result = $this->stockService->parQrCode($qrCode);
        return $this->success([
            'article'        => $result['article'],
            'etat_label'     => $result['etat_label'],
            'categorie_label'=> $result['categorie_label'],
            'en_alerte'      => $result['en_alerte'],
        ]);
    }

    public function mouvement(Request $request, string $id): JsonResponse
    {
        $result = $this->stockService->mouvement($id, $request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        $r = $result;
        return $this->success([
            'article'       => $r['article'],
            'quantite_avant'=> $r['quantite_avant'],
            'quantite_apres'=> $r['quantite_apres'],
            'en_alerte'     => $r['en_alerte'],
        ], "Stock mis à jour : {$r['article']->nom} — {$r['quantite_avant']} → {$r['quantite_apres']} {$r['article']->unite}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $result = $this->stockService->historique($id, $request);
        return $this->paginatedResponse($result['paginator'], 'Historique mouvements', [
            'article' => $result['article'],
        ]);
    }

    public function alertes(): JsonResponse
    {
        $result = $this->stockService->alertes();
        return $this->success([
            'articles'   => $result['articles'],
            'nb_alertes' => $result['nb_alertes'],
        ], "{$result['nb_alertes']} article(s) sous le seuil minimum");
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock/prets",
     *     summary="Enregistrer un nouveau prêt de matériel",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="article_id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"article_id","emprunteur_id","type_emprunteur","nom_emprunteur","quantite","date_pret","date_retour_prevue","note"},
     *             @OA\Property(property="article_id", type="string", format="uuid"),
     *             @OA\Property(property="emprunteur_id", type="string", format="uuid"),
     *             @OA\Property(property="type_emprunteur", type="string", enum={"enseignant","personnel","externe"}),
     *             @OA\Property(property="nom_emprunteur", type="string", maxLength=150),
     *             @OA\Property(property="quantite", type="integer", minimum=1),
     *             @OA\Property(property="date_pret", type="date"),
     *             @OA\Property(property="date_retour_prevue", type="date"),
     *             @OA\Property(property="note", type="string", maxLength=300)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Prêt enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function creerPret(Request $request): JsonResponse
    {
        $result = $this->pretService->creerPret($request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        return $this->created(null, $result['success']);
    }

    public function retourPret(Request $request, string $id): JsonResponse
    {
        $result = $this->pretService->retourPret($id, $request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        return $this->success(null, $result['success']);
    }

    public function indexPrets(Request $request): JsonResponse
    {
        $result = $this->pretService->index($request);
        return $this->paginatedResponse($result['paginator'], 'Prêts récupérés', [
            'nb_en_retard' => $result['enRetard'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock/bons",
     *     summary="Liste des bons de commande",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="statut", in="query", @OA\Schema(type="string", enum={"brouillon","envoye","recu","partiel","annule"})),
     *     @OA\Response(response=200, description="Bons récupérés", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function indexBons(Request $request): JsonResponse
    {
        $result = $this->bonService->index($request);
        return $this->paginatedResponse($result['paginator'], 'Bons de commande récupérés');
    }

    public function creerBon(Request $request): JsonResponse
    {
        $result = $this->bonService->creerBon($request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], null, 400);
        }
        return $this->created($result, "Bon de commande {$result->numero} créé");
    }

    public function statutBon(Request $request, string $id): JsonResponse
    {
        $result = $this->bonService->statutBon($request->all(), $id);
        if (isset($result['error'])) {
            return $this->error($result['error'], null, 409);
        }
        return $this->success($result['bon'] ?? null, "Bon {$result['success'] ?? 'N/A'} — statut : {$result['statut'] ?? 'N/A'}");
    }

    public function pdfBon(string $id): JsonResponse
    {
        $result = $this->bonService->pdfBon($id);
        return $this->success(null, "PDF du bon {$id} généré");
    }

    public function dashboard(): JsonResponse
    {
        $alertes      = $this->stockService->alertes()['nb_alertes'];
        $pretsRetard  = $this->pretService->index(request)['enRetard'];
        $bonsPendants = $this->bonService->index(request)['paginator']->count() - $this->bonService->index(request)->whereIn('statut', ['recu', 'annule'])->count();

        $valeurTotale = ArticleStock::where('actif', true)
            ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
            ->value('total') ?? 0;

        $parCategorie = ArticleStock::where('actif', true)
            ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as qte')
            ->groupBy('categorie')->get();

        $derniersMovements = DB::table('mouvements_stocks')
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