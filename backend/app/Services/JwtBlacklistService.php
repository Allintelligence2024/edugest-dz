<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtBlacklistService
{
    private const CACHE_PREFIX = 'jwt_blacklist:';
    private const CACHE_TTL    = 3600 * 24;

    public function blacklisterTokenCourant(string $raison = 'logout'): void
    {
        try {
            $token   = JWTAuth::getToken();
            $payload = JWTAuth::getPayload($token);

            $jti      = $payload->get('jti') ?? (string) $token;
            $userId   = $payload->get('sub');
            $expireAt = $payload->get('exp');

            $this->blacklister($jti, $userId, $raison, $expireAt);

        } catch (\Throwable $e) {
            Log::warning('JwtBlacklist: impossible de blacklister: ' . $e->getMessage());
        }
    }

    public function blacklisterTousLesTokensUser(string $userId, string $raison = 'account_disabled'): void
    {
        Cache::put(
            "user_tokens_invalidated_at:{$userId}",
            now()->timestamp,
            now()->addDays(30)
        );

        Log::info("JwtBlacklist: tous les tokens de {$userId} invalidés ({$raison})");
    }

    public function estBlackliste(string $jti, string $userId, int $issuedAt): bool
    {
        $cacheKey = self::CACHE_PREFIX . $jti;
        if (Cache::has($cacheKey)) {
            return true;
        }

        $existe = DB::table('jwt_blacklist')->where('jti', $jti)->exists();
        if ($existe) {
            Cache::put($cacheKey, true, self::CACHE_TTL);
            return true;
        }

        $invalidatedAt = Cache::get("user_tokens_invalidated_at:{$userId}");
        if ($invalidatedAt && $issuedAt < $invalidatedAt) {
            return true;
        }

        return false;
    }

    private function blacklister(string $jti, string $userId, string $raison, int $expireAt): void
    {
        DB::table('jwt_blacklist')->insertOrIgnore([
            'jti'            => $jti,
            'user_id'        => $userId,
            'raison'         => $raison,
            'expire_le'      => date('Y-m-d H:i:s', $expireAt),
            'blackliste_le'  => now(),
        ]);

        $ttl = max(0, $expireAt - now()->timestamp);
        Cache::put(self::CACHE_PREFIX . $jti, true, $ttl);

        Log::info("JWT blacklisté", ['jti' => $jti, 'user' => $userId, 'raison' => $raison]);
    }

    public function nettoyerExpires(): int
    {
        return DB::table('jwt_blacklist')
            ->where('expire_le', '<', now())
            ->delete();
    }
}
