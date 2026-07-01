<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Paiement, Eleve};
use App\Services\Paiement\SatimGateway;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Http, Log};
use Illuminate\Support\Str;

class PaiementEnLigneController extends Controller
{
    public function __construct(private SatimGateway $satim) {}

    /**
     * POST /paiements/online/initier
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

        // BaridiMob
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
        $paiement = Paiement::with(['facture:id,numero_facture,total_ttc', 'eleve:id,nom,prenom'])
            ->findOrFail($id);

        if (!$paiement->order_id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NO_ORDER_ID', 'message' => 'Ce paiement ne possède pas d\'order_id Satim'],
            ], 422);
        }

        $statut = $this->satim->getOrderStatus($paiement->order_id);

        if ($statut['success'] && ($statut['order_status'] ?? null) === 2 && $paiement->statut !== 'confirmé') {
            $paiement->update([
                'statut'      => 'confirmé',
                'raw_payload' => array_merge($paiement->raw_payload ?? [], $statut),
            ]);
            $this->finaliserFacture($paiement);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'paiement'        => $paiement->fresh(),
                'satim_response'  => $statut,
                'statut_satim'    => match ($statut['order_status'] ?? null) {
                    0       => 'Enregistré (non payé)',
                    1       => 'Pré-autorisé',
                    2       => 'Payé et confirmé',
                    3       => 'Autorisé',
                    4       => 'Remboursé',
                    5       => 'ACS demandé',
                    6       => 'Refusé',
                    default => 'Inconnu',
                },
            ],
        ]);
    }

    /**
     * POST /api/v1/paiements/online/{id}/rembourser
     */
    public function rembourser(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'motif'   => 'required|string|max:300',
            'montant' => 'nullable|numeric|min:1',
        ]);

        $paiement = Paiement::with('facture')->findOrFail($id);

        if ($paiement->statut !== 'confirmé') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_CONFIRMED', 'message' => 'Seuls les paiements confirmés peuvent être remboursés'],
            ], 422);
        }

        if (!$this->satim->isSandbox() && $paiement->order_id) {
            $result = Http::timeout(15)->post(
                config('services.satim.url') . '/reversalOrder.do',
                [
                    'userName' => config('services.satim.merchant_id'),
                    'password' => config('services.satim.password'),
                    'orderId'  => $paiement->order_id,
                    'language' => 'fr',
                ]
            );
            if (!$result->successful() || ($result->json()['errorCode'] ?? '1') !== '0') {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'REFUND_FAILED', 'message' => 'Remboursement refusé par Satim'],
                ], 502);
            }
        }

        $paiement->update([
            'statut'              => 'remboursé',
            'rembourse_le'        => now(),
            'motif_remboursement' => $validated['motif'],
        ]);

        if ($paiement->facture) {
            $paiement->facture->update(['statut' => 'émise']);
        }

        Log::info('[Satim] Remboursement effectué', [
            'paiement_id' => $paiement->id,
            'motif'       => $validated['motif'],
            'sandbox'     => $this->satim->isSandbox(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $paiement->fresh('facture'),
            'message' => 'Remboursement effectué avec succès',
        ]);
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

        // Notification SMS au parent
        try {
            $eleve = $paiement->eleve?->load('parents');
            if ($eleve) {
                $parent = $eleve->parents->first();
                if ($parent?->telephone_1) {
                    $montantFormate = number_format($paiement->montant, 2, ',', ' ');
                    $typeLabel      = $paiement->type_label;
                    $message = "EduGest DZ : Paiement {$typeLabel} de {$montantFormate} DA "
                             . "reçu pour {$eleve->prenom} {$eleve->nom}. "
                             . "Facture N° {$facture->numero_facture}. Merci.";

                    app(\App\Services\Sms\SmsService::class)->send($parent->telephone_1, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[Satim] SMS confirmation échoué', [
                'paiement_id' => $paiement->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
