<?php

namespace App\Http\Middleware;

use App\Services\JwtBlacklistService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtBlacklistCheck
{
    public function __construct(private JwtBlacklistService $blacklist) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $token   = JWTAuth::getToken();
            if (!$token) return $next($request);

            $payload  = JWTAuth::getPayload($token);
            $jti      = $payload->get('jti') ?? (string) $token;
            $userId   = $payload->get('sub');
            $issuedAt = $payload->get('iat') ?? 0;

            $globalLockTimestamp = Cache::get('global_tokens_invalidated_at');
            if ($globalLockTimestamp && $issuedAt < $globalLockTimestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session invalide suite à un incident de sécurité. Reconnectez-vous.',
                    'code'    => 'GLOBAL_LOCKDOWN',
                ], 401);
            }

            if ($this->blacklist->estBlackliste($jti, $userId, $issuedAt)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expirée ou révoquée. Reconnectez-vous.',
                    'code'    => 'TOKEN_REVOKED',
                ], 401);
            }

        } catch (\Throwable $e) {
        }

        return $next($request);
    }
}
