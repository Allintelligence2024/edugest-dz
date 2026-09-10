<?php

namespace App\Services\Paiement;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SatimGateway
{
    private string $terminalId;
    private string $merchantId;
    private string $password;
    private string $baseUrl;
    private bool   $sandbox;

    public function __construct()
    {
        $config      = config('services.satim');
        $this->terminalId = $config['terminal_id'] ?? '';
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->password   = $config['password'] ?? '';
        $this->baseUrl    = rtrim($config['url'] ?? 'https://test.satim.dz/payment/rest', '/');
        $this->sandbox    = $config['sandbox'] ?? true;
    }

    /**
     * Initier un paiement CIB/Dahabia.
     * Retourne l'URL de redirection Satim.
     */
    public function registerOrder(float $montant, string $reference, string $description, string $retourUrl, string $failUrl): array
    {
        $payload = [
            'userName'       => $this->merchantId,
            'password'       => $this->password,
            'orderNumber'    => $reference,
            'amount'         => (int) round($montant * 100),
            'currency'       => 'DZD',
            'description'    => $description,
            'returnUrl'      => $retourUrl,
            'failUrl'        => $failUrl,
            'language'       => 'fr',
        ];

        if ($this->sandbox) {
            Log::info('[Satim] Sandbox — registerOrder appelé', ['reference' => $reference, 'montant' => $montant]);
            return [
                'success'    => true,
                'order_id'   => 'SANDBOX_' . uniqid(),
                'form_url'   => $retourUrl . '?satim_order_id=SANDBOX_' . uniqid(),
                'sandbox'    => true,
            ];
        }

        $response = Http::timeout(15)->post("{$this->baseUrl}/register.do", $payload);

        if ($response->successful()) {
            $body = $response->json();
            return [
                'success'  => ($body['errorCode'] ?? '1') === '0',
                'order_id' => $body['orderId'] ?? null,
                'form_url' => $body['formUrl'] ?? null,
                'error'    => $body['errorMessage'] ?? null,
            ];
        }

        Log::error('[Satim] registerOrder failed', ['response' => $response->body()]);
        return ['success' => false, 'error' => 'Erreur communication Satim'];
    }

    /**
     * Vérifier le statut d'une commande.
     */
    public function getOrderStatus(string $orderId): array
    {
        if ($this->sandbox) {
            return [
                'success'        => true,
                'order_status'   => 2,
                'order_number'   => $orderId,
            ];
        }

        $response = Http::post("{$this->baseUrl}/getOrderStatus.do", [
            'userName' => $this->merchantId,
            'password' => $this->password,
            'orderId'  => $orderId,
        ]);

        if ($response->successful()) {
            $body = $response->json();
            return [
                'success'      => ($body['errorCode'] ?? '1') === '0',
                'order_status' => $body['orderStatus'] ?? null,
                'order_number' => $body['orderNumber'] ?? null,
                'amount'       => $body['amount'] ?? null,
                'error'        => $body['errorMessage'] ?? null,
            ];
        }

        return ['success' => false, 'error' => 'Erreur vérification statut'];
    }

    /**
     * Confirmer une commande après paiement (si nécessaire).
     */
    public function confirmOrder(string $orderId): array
    {
        if ($this->sandbox) {
            return ['success' => true, 'order_id' => $orderId];
        }

        $response = Http::post("{$this->baseUrl}/confirmOrder.do", [
            'userName' => $this->merchantId,
            'password' => $this->password,
            'orderId'  => $orderId,
        ]);

        if ($response->successful()) {
            $body = $response->json();
            return [
                'success' => ($body['errorCode'] ?? '1') === '0',
                'error'   => $body['errorMessage'] ?? null,
            ];
        }

        return ['success' => false, 'error' => 'Erreur confirmation Satim'];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
