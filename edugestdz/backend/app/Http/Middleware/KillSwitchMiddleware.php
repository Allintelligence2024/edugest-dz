<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class KillSwitchMiddleware
{
    private array $exclusions = [
        'api/health',
        'api/v1/auth/login',
        'api/v1/kill-switch/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (Cache::get('kill_switch:active', false)) {
            foreach ($this->exclusions as $exclusion) {
                if ($request->is($exclusion)) {
                    return $next($request);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Service temporairement indisponible.',
                'code' => 'SERVICE_UNAVAILABLE',
            ], 503);
        }

        return $next($request);
    }
}
