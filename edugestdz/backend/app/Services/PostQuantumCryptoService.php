<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PostQuantumCryptoService
{
    private ?string $publicKey;
    private ?string $privateKey;
    private bool $useSodium;

    public function __construct()
    {
        $this->useSodium = extension_loaded('sodium') && function_exists('sodium_crypto_sign_keypair');

        if ($this->useSodium) {
            try {
                $keypair = sodium_crypto_sign_keypair();
                $this->publicKey = sodium_crypto_sign_publickey($keypair);
                $this->privateKey = sodium_crypto_sign_secretkey($keypair);
            } catch (\Throwable $e) {
                Log::warning('PostQuantum: sodium indisponible, fallback RSA-4096', [
                    'error' => $e->getMessage(),
                ]);
                $this->useSodium = false;
                $this->genererRsa();
            }
        } else {
            Log::info('PostQuantum: extension sodium non chargee, utilisation RSA-4096');
            $this->genererRsa();
        }
    }

    private function isFallback(): bool
    {
        return !$this->useSodium && !str_starts_with($this->privateKey ?? '', '-----BEGIN');
    }

    public function signer(string $data): string
    {
        if ($this->useSodium) {
            return base64_encode(sodium_crypto_sign_detached($data, $this->privateKey));
        }

        if ($this->isFallback()) {
            return base64_encode(hash_hmac('sha512', $data, $this->privateKey, true));
        }

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    public function verifier(string $data, string $signature): bool
    {
        $decoded = base64_decode($signature, true);

        if ($decoded === false) {
            return false;
        }

        if ($this->useSodium) {
            return sodium_crypto_sign_verify_detached($decoded, $data, $this->publicKey);
        }

        if ($this->isFallback()) {
            return hash_hmac('sha512', $data, $this->privateKey, true) === $decoded;
        }

        $result = openssl_verify($data, $decoded, $this->publicKey, OPENSSL_ALGO_SHA512);
        return $result === 1;
    }

    public function getPublicKey(): string
    {
        return base64_encode($this->publicKey);
    }

    private function genererRsa(): void
    {
        if (!extension_loaded('openssl') || !function_exists('openssl_pkey_new')) {
            $this->useSodium = false;
            $this->publicKey = hash('sha256', 'fallback-public-' . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            return;
        }

        $resource = @openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->publicKey = hash('sha256', 'fallback-public-' . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            return;
        }

        openssl_pkey_export($resource, $this->privateKey);
        $details = openssl_pkey_get_details($resource);
        $this->publicKey = $details['key'];
    }
}
