<?php

namespace App\Observers;

use App\Models\AuditChain;
use App\Services\AuditChainService;
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

        if (!is_array($payload)) {
            return;
        }

        $nettoye = $this->nettoyerPayload($payload);

        // Si la caviardage n'a rien changé, ne toucher à RIEN : le service a
        // déjà calculé data_hash ET la signature qui en dépend.
        if ($nettoye === $payload) {
            return;
        }

        $auditChain->payload = $nettoye;

        // Le payload a changé : data_hash et signature doivent être refaits.
        //
        // Bug corrigé (Sprint 3) : cet observer recalculait data_hash avec un
        // json_encode() brut, écrasant systématiquement le hachage canonique
        // du service — y compris quand aucun champ n'était masqué. La
        // signature HMAC, elle, restait calculée sur l'ancien hachage : tout
        // bloc naissait donc avec une signature invalide, et la chaîne était
        // rompue dès le premier enregistrement.
        $service = app(AuditChainService::class);

        $auditChain->data_hash = hash('sha256', $service->encoderPayload($nettoye));
        $auditChain->signature = $service->signer(
            (int) $auditChain->bloc_numero,
            (string) $auditChain->previous_hash,
            (string) $auditChain->data_hash,
            (int) ($auditChain->key_version ?: $service->versionCourante()),
        );
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
