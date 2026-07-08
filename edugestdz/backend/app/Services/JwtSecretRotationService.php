<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JwtSecretRotationService
{
    private const CURRENT_KEY  = 'jwt_secret_current';
    private const PREVIOUS_KEY = 'jwt_secret_previous';
    private const ROTATION_LOG = 'jwt_rotation_log';
    private const GRACE_PERIOD = 3600 * 24;

    public function effectuerRotation(): array
    {
        $nouveauSecret = bin2hex(random_bytes(32));
        $ancienSecret  = config('jwt.secret');

        Cache::put(self::PREVIOUS_KEY, $ancienSecret, self::GRACE_PERIOD);

        $this->enregistrerRotation($ancienSecret, $nouveauSecret);

        Log::info('JWT Secret rotation effectuée', [
            'nouveau_hash' => hash('sha256', $nouveauSecret),
            'ancien_hash'  => hash('sha256', $ancienSecret),
            'grace_period' => self::GRACE_PERIOD . 's',
        ]);

        return [
            'nouveau_secret' => $nouveauSecret,
            'instruction'    => 'Mettre à jour JWT_SECRET dans les variables d\'environnement',
            'grace_until'    => now()->addSeconds(self::GRACE_PERIOD)->toDateTimeString(),
        ];
    }

    public function secretPrecedentValide(): ?string
    {
        return Cache::get(self::PREVIOUS_KEY);
    }

    private function enregistrerRotation(string $ancien, string $nouveau): void
    {
        $log = Cache::get(self::ROTATION_LOG, []);
        $log[] = [
            'date'         => now()->toIso8601String(),
            'ancien_hash'  => hash('sha256', $ancien),
            'nouveau_hash' => hash('sha256', $nouveau),
        ];

        $log = array_slice($log, -10);
        Cache::put(self::ROTATION_LOG, $log, 3600 * 24 * 90);
    }

    public function getHistoriqueRotations(): array
    {
        return Cache::get(self::ROTATION_LOG, []);
    }
}
