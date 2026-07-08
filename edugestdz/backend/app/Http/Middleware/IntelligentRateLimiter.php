<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IntelligentRateLimiter
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!method_exists($response, 'headers')) {
            return $response;
        }

        $limit = 100;
        $window = 60;

        $key = 'ratelimit:' . ($request->user()?->id ?? $request->ip());

        $current = (int) Cache::get($key, 0);
        $remaining = max(0, $limit - $current);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) (time() + $window));

        return $response;
    }
}
