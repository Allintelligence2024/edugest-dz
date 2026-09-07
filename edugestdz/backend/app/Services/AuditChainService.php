<?php

namespace App\Services;

use App\Models\AuditChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditChainService
{
    public function enregistrer(string $event, array $payload, ?string $causerId = null, ?string $causerType = null): AuditChain
    {
        return DB::transaction(function () use ($event, $payload, $causerId, $causerType) {
            $dernier = AuditChain::orderByDesc('bloc_numero')->lockForUpdate()->first();

            $nouveauNumero = $dernier ? $dernier->bloc_numero + 1 : 0;
            $previousHash = $dernier ? $dernier->data_hash : str_repeat('0', 64);

            $fullPayload = array_merge($payload, [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
            ]);

            $payloadJson = $this->encoderPayload($fullPayload);
            $dataHash = hash('sha256', $payloadJson);

            $keyVersion = $this->versionCourante();
            $signature  = $this->signer($nouveauNumero, $previousHash, $dataHash, $keyVersion);

            return AuditChain::create([
                'bloc_numero' => $nouveauNumero,
                'previous_hash' => $previousHash,
                'data_hash' => $dataHash,
                'signature' => $signature,
                'key_version' => $keyVersion,
                'payload' => $fullPayload,
                'causer_id' => $causerId,
                'causer_type' => $causerType,
                'logged_at' => now(),
            ]);
        });
    }

    public function verifierIntegriteComplete(): array
    {
        $results = [
            'total' => 0,
            'invalides' => [],
            'valide' => true,
        ];

        $precedent = null;

        AuditChain::orderBy('bloc_numero')->chunk(1000, function ($blocs) use (&$results, &$precedent) {
            foreach ($blocs as $bloc) {
                $results['total']++;

                if ($bloc->bloc_numero === 0) {
                    $precedent = $bloc;
                    continue;
                }

                $expectedPrevious = $precedent ? $precedent->data_hash : str_repeat('0', 64);
                if ($bloc->previous_hash !== $expectedPrevious) {
                    $results['invalides'][] = [
                        'bloc_numero' => $bloc->bloc_numero,
                        'raison' => 'previous_hash invalide',
                        'attendu' => $expectedPrevious,
                        'trouve' => $bloc->previous_hash,
                    ];
                }

                $expectedDataHash = hash('sha256', $this->encoderPayload($bloc->payload));
                if ($bloc->data_hash !== $expectedDataHash) {
                    $results['invalides'][] = [
                        'bloc_numero' => $bloc->bloc_numero,
                        'raison' => 'data_hash invalide (payload modifie)',
                    ];
                }

                // Vérification de la SIGNATURE HMAC — absente jusqu'ici :
                // sans elle, un attaquant ayant un accès en écriture à la base
                // pouvait recalculer previous_hash et data_hash de façon
                // cohérente et réécrire l'historique sans être détecté. Seule
                // la signature, qui dépend d'un secret hors base, l'en empêche.
                if (!$this->signatureValide($bloc)) {
                    $results['invalides'][] = [
                        'bloc_numero' => $bloc->bloc_numero,
                        'raison' => 'signature HMAC invalide (bloc forge ou cle incorrecte)',
                    ];
                }

                $precedent = $bloc;
            }
        });

        $results['valide'] = empty($results['invalides']);

        return $results;
    }

    public function obtenirDernierBloc(): ?AuditChain
    {
        return AuditChain::orderByDesc('bloc_numero')->first();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // SIGNATURE & ROTATION DE CLÉ
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Encodage canonique du payload.
     *
     * json_encode ne garantit pas un ordre stable si le tableau est
     * reconstruit differemment (ex. relecture depuis JSONB PostgreSQL, qui
     * réordonne les clés). On trie donc récursivement avant de hacher, sans
     * quoi la vérification produirait de faux positifs.
     */
    public function encoderPayload(mixed $payload): string
    {
        $normalise = $this->trierRecursivement($payload);

        return json_encode($normalise, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function trierRecursivement(mixed $valeur): mixed
    {
        if (!is_array($valeur)) {
            return $valeur;
        }

        $trie = array_map(fn ($v) => $this->trierRecursivement($v), $valeur);

        // Uniquement pour les tableaux associatifs : préserver l'ordre des
        // listes indexées, qui est signifiant.
        if (!array_is_list($trie)) {
            ksort($trie);
        }

        return $trie;
    }

    /** Version de clé utilisée pour signer les nouveaux blocs. */
    public function versionCourante(): int
    {
        return (int) config('security.audit.key_version', 1);
    }

    /**
     * Clé HMAC d'une version donnée.
     *
     * Volontairement distincte de APP_KEY : une rotation d'APP_KEY ne doit
     * jamais invalider la chaîne d'audit.
     */
    private function cle(int $version): ?string
    {
        $cles = config('security.audit.keys', []);
        $cle  = $cles[$version] ?? null;

        if (!$cle) {
            return null;
        }

        if (str_starts_with($cle, 'base64:')) {
            $cle = base64_decode(substr($cle, 7)) ?: $cle;
        }

        return $cle;
    }

    public function signer(int $blocNumero, string $previousHash, string $dataHash, int $keyVersion): string
    {
        $cle = $this->cle($keyVersion);

        if ($cle === null) {
            throw new \RuntimeException(
                "Cle de signature d'audit introuvable pour la version {$keyVersion}. "
                . 'Definir AUDIT_CHAIN_KEY dans l\'environnement.'
            );
        }

        return hash_hmac(
            'sha256',
            $blocNumero . ':' . $previousHash . ':' . $dataHash,
            $cle
        );
    }

    /** La signature d'un bloc correspond-elle a la cle de SA version ? */
    public function signatureValide(AuditChain $bloc): bool
    {
        // Le bloc genesis est cree par la migration avec un simple sha256.
        if ((int) $bloc->bloc_numero === 0) {
            return true;
        }

        $version = (int) ($bloc->key_version ?: 1);
        $cle     = $this->cle($version);

        if ($cle === null) {
            Log::warning('AuditChain: cle absente pour la version demandee', [
                'bloc_numero' => $bloc->bloc_numero,
                'key_version' => $version,
            ]);

            return false;
        }

        $attendue = $this->signer(
            (int) $bloc->bloc_numero,
            $bloc->previous_hash,
            $bloc->data_hash,
            $version
        );

        return hash_equals($attendue, (string) $bloc->signature);
    }
}
