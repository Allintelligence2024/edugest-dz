<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrCodeService
{
    private const CACHE_TTL = 3600;
    private const TOKEN_PREFIX = 'qr_session:';
    private const SESSION_PREFIX = 'qr_session_active:';

    public function genererTokenSession(string $seanceId, string $tenantId): string
    {
        $token = Str::random(32) . '_' . now()->timestamp;
        $cleCache = self::TOKEN_PREFIX . $seanceId;

        try {
            Cache::put($cleCache, [
                'token'     => $token,
                'tenant_id' => $tenantId,
                'expire'    => now()->addSeconds(self::CACHE_TTL),
            ], self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: cache write failed: " . $e->getMessage());
        }

        return $token;
    }

    public function validerTokenSession(string $token, string $seanceId): ?array
    {
        $cleCache = self::TOKEN_PREFIX . $seanceId;

        try {
            $session = Cache::get($cleCache);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: cache read failed: " . $e->getMessage());
            return null;
        }

        if (!$session || $session['token'] !== $token) {
            return null;
        }

        if (now()->gt($session['expire'])) {
            return null;
        }

        return [
            'seance_id' => $seanceId,
            'tenant_id' => $session['tenant_id'],
            'expire'    => $session['expire'],
        ];
    }

    public function demarrerSession(string $seanceId, string $tenantId): array
    {
        $token = $this->genererTokenSession($seanceId, $tenantId);
        $cleSession = self::SESSION_PREFIX . $seanceId;

        try {
            Cache::put($cleSession, [
                'active'     => true,
                'started_at' => now()->toIso8601String(),
                'tenant_id'  => $tenantId,
            ], self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session cache failed: " . $e->getMessage());
        }

        return [
            'token'      => $token,
            'seance_id'  => $seanceId,
            'started_at' => now()->toIso8601String(),
            'expires_in' => self::CACHE_TTL,
        ];
    }

    public function estSessionActive(string $seanceId): bool
    {
        $cleSession = self::SESSION_PREFIX . $seanceId;

        try {
            $session = Cache::get($cleSession);
            return $session && ($session['active'] ?? false);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session check failed: " . $e->getMessage());
            return false;
        }
    }

    public function fermerSession(string $seanceId): void
    {
        try {
            Cache::forget(self::TOKEN_PREFIX . $seanceId);
            Cache::forget(self::SESSION_PREFIX . $seanceId);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session close failed: " . $e->getMessage());
        }
    }
}
