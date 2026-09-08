<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôle d'accès par rôle — Sprint 2 (RBAC).
 *
 * Usage dans les routes :
 *   Route::middleware('role:admin,comptable')->group(...)
 *
 * Complète l'isolation multi-tenant : ResolveTenant garantit qu'un
 * utilisateur ne voit que SON établissement ; ce middleware garantit
 * qu'à l'intérieur de cet établissement, il ne touche que ce que son
 * rôle autorise (un parent ne doit pas atteindre /paies ou /finance).
 *
 * Fail-closed : sans utilisateur authentifié ou sans rôle, on refuse.
 */
class RoleCheck
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentification requise'],
            ], 401);
        }

        // Le super_admin traverse toutes les barrières de rôle métier.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->hasRole(...$roles)) {
            Log::warning('RBAC: accès refusé (rôle)', [
                'user_id'        => $user->id,
                'tenant_id'      => $user->tenant_id,
                'role'           => $user->nomRole(),
                'roles_requis'   => $roles,
                'methode'        => $request->method(),
                'chemin'         => $request->path(),
                'ip'             => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'FORBIDDEN',
                    'message' => "Votre rôle ne vous permet pas d'accéder à cette ressource",
                ],
            ], 403);
        }

        return $next($request);
    }
}
