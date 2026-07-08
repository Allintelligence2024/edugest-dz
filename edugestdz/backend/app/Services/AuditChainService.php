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

            $payloadJson = json_encode($fullPayload);
            $dataHash = hash('sha256', $payloadJson);
            $signature = hash_hmac('sha256', $nouveauNumero . ':' . $previousHash . ':' . $dataHash, config('app.key'));

            return AuditChain::create([
                'bloc_numero' => $nouveauNumero,
                'previous_hash' => $previousHash,
                'data_hash' => $dataHash,
                'signature' => $signature,
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

                $expectedDataHash = hash('sha256', json_encode($bloc->payload));
                if ($bloc->data_hash !== $expectedDataHash) {
                    $results['invalides'][] = [
                        'bloc_numero' => $bloc->bloc_numero,
                        'raison' => 'data_hash invalide (payload modifie)',
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
}
