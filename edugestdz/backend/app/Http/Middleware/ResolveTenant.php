<?php
// backend/app/Http/Middleware/ResolveTenant.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        // ── Récupérer l'utilisateur connecté ──
        $user = Auth::user();

        // 403 et non 404 : un 404 indiquerait à un attaquant que la
        // ressource n'existe pas, alors que la vraie raison est un défaut
        // d'habilitation. On ne divulgue pas d'information d'existence.
        if (!$user || !$user->tenant_id) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TENANT_FORBIDDEN',
                    'message' => 'Accès à cet établissement non autorisé'
                ]
            ], 403);
        }

        // ── Charger le tenant ──
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TENANT_FORBIDDEN',
                    'message' => 'Accès à cet établissement non autorisé'
                ]
            ], 403);
        }

        // ── Mettre le tenant en contexte global ──
        app()->instance('tenant', $tenant);
        $request->merge(['tenant_id' => $tenant->id]);

        // ── Scope global automatique ──
        config(['tenant.current_id' => $tenant->id]);

        return $next($request);
    }
}
