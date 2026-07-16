<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IntelligentRateLimiter
{
    private const ROLE_LIMITS = [
        'super_admin' => 1000,
        'admin'       => 200,
        'enseignant'  => 100,
        'user'        => 60,
        'anonymous'   => 30,
    ];

    private const DEFAULT_LIMIT = 60;
    private const WINDOW = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $role  = $this->resolveRole($request);
        $limit = self::ROLE_LIMITS[$role] ?? self::DEFAULT_LIMIT;
        $window = self::WINDOW;

        $key = 'ratelimit:' . ($request->user()?->id ?? $request->ip());

        $current   = (int) Cache::get($key, 0);
        $remaining = max(0, $limit - $current);
        $resetAt   = time() + $window;

        if ($current >= $limit) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Trop de requêtes. Réessayez dans quelques secondes.',
                ],
            ], 429)->withHeaders([
                'X-RateLimit-Limit'     => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset'     => (string) $resetAt,
                'Retry-After'           => (string) $window,
            ]);
        }

        Cache::put($key, $current + 1, $window);

        /** @var Response $response */
        $response = $next($request);

        if (method_exists($response, 'headers')) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit);
            $response->headers->set('X-RateLimit-Remaining', (string) ($remaining - 1));
            $response->headers->set('X-RateLimit-Reset', (string) $resetAt);
        }

        return $response;
    }

    private function resolveRole(Request $request): string
    {
        $user = $request->user();
        if (!$user) return 'anonymous';

        $role = $user->role;
        return is_object($role) ? ($role->nom ?? 'user') : ($role ?? 'user');
    }
}
