<?php

namespace App\Http\Middleware;

use App\Services\KillSwitchService;
use Closure;
use Illuminate\Http\Request;

class KillSwitchMiddleware
{
    private array $exclusions = [
        'api/health',
        'api/v1/auth/login',
        'api/v1/kill-switch/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (app(KillSwitchService::class)->estActif()) {
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
