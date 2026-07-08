<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantIsolationVerifier
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if (!$user) return $next($request);

        $headerTenantId = $request->header('X-Tenant-ID');
        $userTenantId   = $user->tenant_id;
        $configTenantId = config('tenant.current_id');

        if ($headerTenantId && $userTenantId && $headerTenantId !== $userTenantId) {
            Log::critical('TENANT HEADER MANIPULATION DETECTED', [
                'user_id'        => $user->id,
                'user_tenant'    => $userTenantId,
                'header_tenant'  => $headerTenantId,
                'ip'             => $request->ip(),
                'path'           => $request->path(),
                'user_agent'     => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : manipulation de tenant détectée.',
                'code'    => 'TENANT_MANIPULATION',
            ], 403);
        }

        if ($user->role?->nom !== 'super_admin' && $configTenantId && $userTenantId) {
            if ($configTenantId !== $userTenantId) {
                Log::critical('CROSS-TENANT ACCESS ATTEMPT', [
                    'user_id'       => $user->id,
                    'user_tenant'   => $userTenantId,
                    'config_tenant' => $configTenantId,
                    'ip'            => $request->ip(),
                    'path'          => $request->path(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : isolation tenant violée.',
                    'code'    => 'CROSS_TENANT_ACCESS',
                ], 403);
            }
        }

        return $next($request);
    }
}
