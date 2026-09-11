<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\PaiementConfirmationService;
use App\Services\PaiementInitiationService;
use App\Services\Paiement\SatimGateway;
use Illuminate\Http\{Request, JsonResponse};

class PaiementEnLigneController extends Controller
{
    public function __construct(
        private PaiementInitiationService $initiation,
        private PaiementConfirmationService $confirmation,
        private SatimGateway $satim,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/paiements/online/initier",
     *     summary="Initier un paiement en ligne (CIB / Dahabia / BaridiMob)",
     *     tags={"PaiementCIB"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"facture_id","type_paiement"},
     *             @OA\Property(property="facture_id",    type="string", format="uuid"),
     *             @OA\Property(property="type_paiement", type="string", enum={"cib","dahabia","baridimob"}),
     *             @OA\Property(property="montant",       type="number", format="float", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL de redirection ou référence générée",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="redirect_url", type="string", format="uri"),
     *                 @OA\Property(property="order_id",     type="string"),
     *                 @OA\Property(property="paiement",     type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Facture introuvable ou déjà payée", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function initier(Request $request): JsonResponse
    {
        $result = $this->initiation->initier($request->all());

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], $result['status']);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'message' => $result['message'],
        ]);
    }

    /**
     * GET /paiements/online/retour
     */
    public function retour(Request $request): JsonResponse
    {
        $result = $this->confirmation->retour($request);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['paiement' => $result['paiement']],
            'message' => $result['message'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/paiements/online/callback",
     *     summary="Callback Satim (webhook confirmation paiement)",
     *     tags={"PaiementCIB"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="orderId",       type="string"),
     *             @OA\Property(property="orderNumber",   type="string"),
     *             @OA\Property(property="orderStatus",   type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Paiement confirmé",  @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=400, description="Paiement échoué",    @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function callback(Request $request): JsonResponse
    {
        $result = $this->confirmation->callback($request);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], $result['status']);
        }

        return response()->json(['success' => true, 'message' => $result['message']]);
    }

    /**
     * GET /api/v1/paiements/online/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);

        $paiementsEnLigne = Paiement::enLigne()
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->get();

        $stats = [
            'total_transactions' => $paiementsEnLigne->count(),
            'confirmes'         => $paiementsEnLigne->where('statut', 'confirmé')->count(),
            'en_attente'        => $paiementsEnLigne->where('statut', 'en_attente')->count(),
            'annules'           => $paiementsEnLigne->where('statut', 'annulé')->count(),
            'rembourses'        => $paiementsEnLigne->where('statut', 'remboursé')->count(),
            'montant_total'     => (float) $paiementsEnLigne->where('statut', 'confirmé')->sum('montant'),
            'par_type'          => $paiementsEnLigne->where('statut', 'confirmé')
                ->groupBy('type_paiement')
                ->map(fn($g) => ['count' => $g->count(), 'montant' => (float) $g->sum('montant')]),
            'sandbox_actif'     => $this->satim->isSandbox(),
        ];

        $derniersPayments = Paiement::enLigne()
            ->with('facture:id,numero_facture', 'eleve:id,nom,prenom')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'          => compact('mois', 'annee'),
                'stats'            => $stats,
                'derniers_paiements'=> $derniersPayments,
            ],
        ]);
    }

    /**
     * GET /api/v1/paiements/online/{id}/statut
     */
    public function verifierStatut(string $id): JsonResponse
    {
        $result = $this->confirmation->verifierStatut($id);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'paiement'        => $result['paiement'],
                'satim_response'  => $result['satim_response'],
                'statut_satim'    => $result['statut_satim'],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/paiements/online/{id}/rembourser",
     *     summary="Rembourser un paiement en ligne",
     *     tags={"PaiementCIB"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif"},
     *             @OA\Property(property="motif", type="string", example="Désinscription élève")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Remboursement initié", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=422, description="Paiement non remboursable", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function rembourser(Request $request, string $id): JsonResponse
    {
        $result = $this->confirmation->rembourser($id, $request->all());

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], $result['status']);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['paiement'],
            'message' => $result['message'],
        ]);
    }
}
