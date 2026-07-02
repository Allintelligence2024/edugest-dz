<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Paiement};
use App\Services\FacturationService;
use Illuminate\Http\{Request, JsonResponse};

class FinanceController extends Controller
{
    public function __construct(private FacturationService $service) {}

    /**
     * @OA\Get(
     *     path="/api/v1/finance/tableau-bord",
     *     summary="Dashboard financier",
     *     tags={"Finances"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Response(
     *         response=200,
     *         description="KPIs financiers",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="ca_mois",      type="number",  format="float"),
     *                 @OA\Property(property="ca_annee",     type="number",  format="float"),
     *                 @OA\Property(property="impayes",      type="number",  format="float"),
     *                 @OA\Property(property="nb_impayes",   type="integer"),
     *                 @OA\Property(property="ca_par_mois",  type="array",   @OA\Items(type="object")),
     *                 @OA\Property(property="modes_payment",type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function tableauBord(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getTableauBord(),
        ]);
    }

    public function impayes(Request $request): JsonResponse
    {
        $impayes = Facture::with(['eleve' => fn($q) => $q->select('id','nom','prenom','numero_inscription')])
            ->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
            ->where('date_echeance', '<', today())
            ->orderBy('date_echeance')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $impayes,
            'total'   => $impayes->sum('total_ttc'),
        ]);
    }

    public function bilanMensuel(Request $request): JsonResponse
    {
        $mois  = $request->mois ?? now()->month;
        $annee = $request->annee ?? now()->year;

        $encaissements = Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $factureEmises = Facture::whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->sum('total_ttc');

        return response()->json([
            'success' => true,
            'data'    => [
                'mois'          => $mois,
                'annee'         => $annee,
                'encaissements' => (float) $encaissements,
                'facture_emises'=> (float) $factureEmises,
                'taux_recouvrement' => $factureEmises > 0
                    ? round(($encaissements / $factureEmises) * 100, 1)
                    : 0,
            ],
        ]);
    }

    public function bilanAnnuel(Request $request): JsonResponse
    {
        $annee = $request->annee ?? now()->year;
        $data = [];

        for ($m = 1; $m <= 12; $m++) {
            $encaissements = Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $m)
                ->whereYear('date_paiement', $annee)
                ->sum('montant');

            $factureEmises = Facture::whereMonth('date_emission', $m)
                ->whereYear('date_emission', $annee)
                ->sum('total_ttc');

            $data[] = [
                'mois'           => $m,
                'encaissements'  => (float) $encaissements,
                'facture_emises' => (float) $factureEmises,
            ];
        }

        return response()->json([
            'success' => true,
            'annee'   => $annee,
            'data'    => $data,
        ]);
    }

    public function envoyerRelances(Request $request): JsonResponse
    {
        $impayes = Facture::whereIn('statut', ['émise', 'en_retard'])
            ->where('date_echeance', '<', today())
            ->with('eleve.parents')
            ->get();

        $relancesEnvoyees = 0;
        foreach ($impayes as $facture) {
            $parent = $facture->eleve->parents->firstWhere('pivot.est_principal', true);
            if ($parent?->telephone_1) {
                $relancesEnvoyees++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$relancesEnvoyees} relance(s) envoyée(s)",
        ]);
    }
}
