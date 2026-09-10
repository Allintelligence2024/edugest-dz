<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôle d'accès par permission fine — Sprint 2 (RBAC).
 *
 * Usage :
 *   Route::middleware('permission:factures.créer')->post(...)
 *
 * S'appuie sur la table `role_permissions` alimentée par RolePermissionSeeder
 * (86 permissions au format "module.action"), jusqu'ici seedée mais JAMAIS
 * vérifiée nulle part dans l'application.
 *
 * Plusieurs permissions = OR logique (l'une d'elles suffit).
 */
class PermissionCheck
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentification requise'],
            ], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        Log::warning('RBAC: accès refusé (permission)', [
            'user_id'            => $user->id,
            'tenant_id'          => $user->tenant_id,
            'role'               => $user->nomRole(),
            'permissions_requises' => $permissions,
            'methode'            => $request->method(),
            'chemin'             => $request->path(),
            'ip'                 => $request->ip(),
        ]);

        return response()->json([
            'success' => false,
            'error'   => [
                'code'    => 'FORBIDDEN',
                'message' => "Permission insuffisante pour cette opération",
            ],
        ], 403);
    }
}
