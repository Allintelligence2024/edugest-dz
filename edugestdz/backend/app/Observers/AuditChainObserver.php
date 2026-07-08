<?php

namespace App\Observers;

use App\Models\AuditChain;
use Illuminate\Support\Facades\Log;

class AuditChainObserver
{
    private array $champsExclus = [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'jwt_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'secret',
        'access_token',
        'refresh_token',
    ];

    public function creating(AuditChain $auditChain): void
    {
        $payload = $auditChain->payload;

        if (is_array($payload)) {
            $payload = $this->nettoyerPayload($payload);
            $auditChain->payload = $payload;
            $auditChain->data_hash = hash('sha256', json_encode($payload));
        }
    }

    private function nettoyerPayload(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $this->champsExclus, true)) {
                $data[$key] = '[REDACTED]';
                Log::info('AuditChain: champ sensible masque', ['key' => $key]);
            }

            if (is_array($value)) {
                $data[$key] = $this->nettoyerPayload($value);
            }
        }

        return $data;
    }
}
