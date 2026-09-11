<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VaultSecretsService
{
    private ?string $vaultAddr;
    private ?string $vaultToken;
    private bool $useVault;

    public function __construct()
    {
        $this->vaultAddr = env('VAULT_ADDR');
        $this->vaultToken = env('VAULT_TOKEN');
        $this->useVault = !empty($this->vaultAddr) && !empty($this->vaultToken);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->useVault) {
            try {
                return $this->getFromVault($key) ?? $default;
            } catch (\Throwable $e) {
                Log::error('Vault: erreur de lecture, fallback BDD', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                return $this->getFromDatabase($key) ?? $default;
            }
        }

        return $this->getFromDatabase($key) ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $encoded = is_string($value) ? $value : json_encode($value);

        if ($this->useVault) {
            try {
                $this->setToVault($key, $encoded);
                return;
            } catch (\Throwable $e) {
                Log::error('Vault: erreur d\'ecriture, fallback BDD', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->setToDatabase($key, $encoded);
    }

    private function getFromVault(string $key): ?string
    {
        $url = rtrim($this->vaultAddr, '/') . '/v1/secret/data/' . $key;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Vault-Token: ' . $this->vaultToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['data']['data'][$key] ?? null;
    }

    private function setToVault(string $key, string $value): void
    {
        $url = rtrim($this->vaultAddr, '/') . '/v1/secret/data/' . $key;

        $payload = json_encode(['data' => [$key => $value]]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'X-Vault-Token: ' . $this->vaultToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function getFromDatabase(string $key): ?string
    {
        $secret = DB::table('encrypted_secrets')
            ->where('key', $key)
            ->first();

        if (!$secret) {
            return null;
        }

        try {
            return Crypt::decryptString($secret->value);
        } catch (\Throwable $e) {
            Log::error('Vault: erreur decryptage BDD', ['key' => $key]);
            return null;
        }
    }

    private function setToDatabase(string $key, string $value): void
    {
        $encrypted = Crypt::encryptString($value);

        DB::table('encrypted_secrets')->updateOrInsert(
            ['key' => $key],
            [
                'id' => Str::uuid()->toString(),
                'value' => $encrypted,
                'updated_at' => now(),
            ]
        );
    }
}
