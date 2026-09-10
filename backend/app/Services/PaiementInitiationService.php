<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\Paiement;
use App\Services\Paiement\SatimGateway;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaiementInitiationService
{
    public function __construct(private SatimGateway $satim) {}

    /**
     * @param array $data
     * @return array{error: array{code: string, message: mixed}, status: int}|array{data: array, message: string}
     */
    public function initier(array $data): array
    {
        $validated = Validator::make($data, [
            'facture_id'    => 'required|uuid|exists:factures,id',
            'type_paiement' => 'required|in:cib,dahabia,baridimob',
            'montant'       => 'nullable|numeric|min:100',
        ])->validate();

        $facture = Facture::findOrFail($validated['facture_id']);

        if (in_array($facture->statut, ['payée', 'annulée'])) {
            return [
                'error'  => ['code' => 'E004', 'message' => 'Cette facture est déjà payée ou annulée'],
                'status' => 422,
            ];
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
                return [
                    'error'  => ['code' => 'PAYMENT_GATEWAY_ERROR', 'message' => $result['error'] ?? 'Erreur passerelle Satim'],
                    'status' => 502,
                ];
            }

            $paiement->update([
                'order_id'    => $result['order_id'],
                'raw_payload' => json_encode($result),
            ]);

            return [
                'data'    => [
                    'paiement'     => $paiement->fresh(),
                    'redirect_url' => $result['form_url'],
                    'order_id'     => $result['order_id'],
                ],
                'message' => 'Redirection vers la page de paiement',
            ];
        }

        // BaridiMob
        return [
            'data'    => [
                'paiement'     => $paiement->fresh(),
                'reference'    => $reference,
                'montant'      => $montant,
                'instructions' => 'Effectuez le virement BaridiMob vers le compte EduGest DZ avec la référence ci-dessus.',
            ],
            'message' => 'Référence BaridiMob générée',
        ];
    }
}
