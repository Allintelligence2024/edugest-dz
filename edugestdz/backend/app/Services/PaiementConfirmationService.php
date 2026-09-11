<?php

namespace App\Services;

use App\Models\Paiement;
use App\Services\Paiement\SatimGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaiementConfirmationService
{
    public function __construct(private SatimGateway $satim) {}

    /**
     * @return array{error: array{code: string, message: string}}|array{paiement: Paiement, message: string}
     */
    public function retour(Request $request): array
    {
        $reference = $request->query('reference');
        $orderId   = $request->query('satim_order_id');
        $echec     = $request->query('echec');

        $paiement = Paiement::where('reference_trans', $reference)->firstOrFail();

        if ($echec || !$orderId) {
            $paiement->update(['statut' => 'annulé']);
            return ['error' => ['code' => 'PAYMENT_CANCELLED', 'message' => 'Paiement annulé']];
        }

        $statut = $this->satim->getOrderStatus($orderId);

        if ($statut['success'] && ($statut['order_status'] ?? null) === 2) {
            $paiement->update([
                'statut'      => 'confirmé',
                'raw_payload' => json_encode($statut),
            ]);
            $this->finaliserFacture($paiement);

            return ['paiement' => $paiement->fresh(), 'message' => 'Paiement confirmé'];
        }

        $paiement->update(['statut' => 'annulé', 'raw_payload' => json_encode($statut)]);
        return ['error' => ['code' => 'PAYMENT_FAILED', 'message' => 'Paiement non confirmé']];
    }

    /**
     * @return array{error: string|array{code: string, message: string}, status: int}|array{message: string}
     */
    public function callback(Request $request): array
    {
        $orderId     = $request->input('orderId');
        $orderNumber = $request->input('orderNumber');

        if (!$orderId || !$orderNumber) {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $paiement = Paiement::where('reference_trans', $orderNumber)
            ->orWhere('order_id', $orderId)
            ->first();

        if (!$paiement) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if ($paiement->statut === 'confirmé') {
            return [
                'error'  => ['code' => 'E004', 'message' => 'Paiement déjà effectué'],
                'status' => 422,
            ];
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

        return ['message' => 'Notification traitée'];
    }

    /**
     * @return array{error: array{code: string, message: string}}|array{paiement: Paiement, satim_response: mixed, statut_satim: string}
     */
    public function verifierStatut(string $id): array
    {
        $paiement = Paiement::with(['facture:id,numero_facture,total_ttc', 'eleve:id,nom,prenom'])
            ->findOrFail($id);

        if (!$paiement->order_id) {
            return ['error' => ['code' => 'NO_ORDER_ID', 'message' => 'Ce paiement ne possède pas d\'order_id Satim']];
        }

        $statut = $this->satim->getOrderStatus($paiement->order_id);

        if ($statut['success'] && ($statut['order_status'] ?? null) === 2 && $paiement->statut !== 'confirmé') {
            $paiement->update([
                'statut'      => 'confirmé',
                'raw_payload' => array_merge($paiement->raw_payload ?? [], $statut),
            ]);
            $this->finaliserFacture($paiement);
        }

        return [
            'paiement'       => $paiement->fresh(),
            'satim_response' => $statut,
            'statut_satim'   => match ($statut['order_status'] ?? null) {
                0       => 'Enregistré (non payé)',
                1       => 'Pré-autorisé',
                2       => 'Payé et confirmé',
                3       => 'Autorisé',
                4       => 'Remboursé',
                5       => 'ACS demandé',
                6       => 'Refusé',
                default => 'Inconnu',
            },
        ];
    }

    /**
     * @param array $data
     * @return array{error: array{code: string, message: string}, status: int}|array{paiement: Paiement, message: string}
     */
    public function rembourser(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'motif'   => 'required|string|max:300',
            'montant' => 'nullable|numeric|min:1',
        ])->validate();

        $paiement = Paiement::with('facture')->findOrFail($id);

        if ($paiement->statut !== 'confirmé') {
            return [
                'error'  => ['code' => 'NOT_CONFIRMED', 'message' => 'Seuls les paiements confirmés peuvent être remboursés'],
                'status' => 422,
            ];
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
                return [
                    'error'  => ['code' => 'REFUND_FAILED', 'message' => 'Remboursement refusé par Satim'],
                    'status' => 502,
                ];
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

        return ['paiement' => $paiement->fresh('facture'), 'message' => 'Remboursement effectué avec succès'];
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
