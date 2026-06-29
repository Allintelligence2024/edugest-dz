<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Paiement, Eleve};
use App\Services\Paiement\SatimGateway;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaiementEnLigneController extends Controller
{
    public function __construct(private SatimGateway $satim) {}

    /**
     * POST /paiements/online/initier
     * Initie un paiement en ligne via Satim (CIB/Dahabia) ou génère
     * une référence BaridiMob.
     */
    public function initier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facture_id'    => 'required|uuid|exists:factures,id',
            'type_paiement' => 'required|in:cib,dahabia,baridimob',
            'montant'       => 'nullable|numeric|min:100',
        ]);

        $facture = Facture::findOrFail($validated['facture_id']);

        if (in_array($facture->statut, ['payée', 'annulée'])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'E004', 'message' => 'Cette facture est déjà payée ou annulée'],
            ], 422);
        }

        $montant = $validated['montant'] ?? $facture->total_ttc;
        $reference = 'PAY-' . strtoupper(Str::random(12));

        $paiement = Paiement::create([
            'tenant_id'     => config('tenant.current_id'),
            'facture_id'    => $facture->id,
            'eleve_id'      => $facture->eleve_id,
            'montant'       => $montant,
            'mode_paiement' => $validated['type_paiement'],
            'reference_trans' => $reference,
            'statut'        => 'en_attente',
            'mode'          => 'en_ligne',
            'type_paiement' => $validated['type_paiement'],
            'date_paiement' => now(),
        ]);

        if (in_array($validated['type_paiement'], ['cib', 'dahabia'])) {
            $retourUrl = url("/api/v1/paiements/online/retour?reference={$reference}");
            $failUrl   = url("/api/v1/paiements/online/retour?reference={$reference}&echec=1");

            $result = $this->satim->registerOrder(
                montant: $montant,
                reference: $reference,
                description: "Paiement facture {$facture->numero_facture}",
                retourUrl: $retourUrl,
                failUrl: $failUrl,
            );

            if (!$result['success']) {
                $paiement->update(['statut' => 'annulé', 'raw_payload' => json_encode($result)]);
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'PAYMENT_GATEWAY_ERROR', 'message' => $result['error'] ?? 'Erreur passerelle Satim'],
                ], 502);
            }

            $paiement->update([
                'order_id'    => $result['order_id'],
                'raw_payload' => json_encode($result),
            ]);

            return response()->json([
                'success'      => true,
                'data'         => [
                    'paiement'    => $paiement->fresh(),
                    'redirect_url' => $result['form_url'],
                    'order_id'     => $result['order_id'],
                ],
                'message'      => 'Redirection vers la page de paiement',
            ]);
        }

        // BaridiMob : flux référence à payer
        return response()->json([
            'success' => true,
            'data'    => [
                'paiement'      => $paiement->fresh(),
                'reference'     => $reference,
                'montant'       => $montant,
                'instructions'  => 'Effectuez le virement BaridiMob vers le compte EduGest DZ avec la référence ci-dessus.',
            ],
            'message' => 'Référence BaridiMob générée',
        ]);
    }

    /**
     * GET /paiements/online/retour
     * Page de retour après redirection Satim (navigateur).
     */
    public function retour(Request $request): JsonResponse
    {
        $reference = $request->query('reference');
        $orderId   = $request->query('satim_order_id');
        $echec     = $request->query('echec');

        $paiement = Paiement::where('reference_trans', $reference)->firstOrFail();

        if ($echec || !$orderId) {
            $paiement->update(['statut' => 'annulé']);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PAYMENT_CANCELLED', 'message' => 'Paiement annulé'],
            ]);
        }

        $statut = $this->satim->getOrderStatus($orderId);

        if ($statut['success'] && ($statut['order_status'] ?? null) === 2) {
            $paiement->update([
                'statut'      => 'confirmé',
                'raw_payload' => json_encode($statut),
            ]);
            $this->finaliserFacture($paiement);

            return response()->json([
                'success' => true,
                'data'    => ['paiement' => $paiement->fresh()],
                'message' => 'Paiement confirmé',
            ]);
        }

        $paiement->update(['statut' => 'annulé', 'raw_payload' => json_encode($statut)]);
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'PAYMENT_FAILED', 'message' => 'Paiement non confirmé'],
        ]);
    }

    /**
     * POST /paiements/online/callback
     * Notification serveur Satim (IPN).
     */
    public function callback(Request $request): JsonResponse
    {
        $orderId   = $request->input('orderId');
        $orderNumber = $request->input('orderNumber');

        if (!$orderId || !$orderNumber) {
            return response()->json(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }

        $paiement = Paiement::where('reference_trans', $orderNumber)
            ->orWhere('order_id', $orderId)
            ->first();

        if (!$paiement) {
            return response()->json(['success' => false, 'error' => 'Transaction introuvable'], 404);
        }

        if ($paiement->statut === 'confirmé') {
            return response()->json(['success' => false, 'error' => ['code' => 'E004', 'message' => 'Paiement déjà effectué']], 422);
        }

        $statut = $this->satim->getOrderStatus($orderId);

        if ($statut['success'] && ($statut['order_status'] ?? null) === 2) {
            $paiement->update([
                'statut'      => 'confirmé',
                'raw_payload' => json_encode($statut),
            ]);
            $this->finaliserFacture($paiement);
        } else {
            $paiement->update(['statut' => 'annulé', 'raw_payload' => json_encode($statut)]);
        }

        return response()->json(['success' => true, 'message' => 'Notification traitée']);
    }

    private function finaliserFacture(Paiement $paiement): void
    {
        $facture = $paiement->facture;

        if (!$facture) return;

        $totalPaye = $facture->paiements()
            ->where('statut', 'confirmé')
            ->sum('montant');

        if ($totalPaye >= $facture->total_ttc) {
            $facture->update(['statut' => 'payée']);
        } elseif ($totalPaye > 0) {
            $facture->update(['statut' => 'partiellement_payée']);
        }
    }
}
