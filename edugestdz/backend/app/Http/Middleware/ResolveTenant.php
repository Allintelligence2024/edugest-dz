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

        if (!$user || !$user->tenant_id) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TENANT_NOT_FOUND',
                    'message' => 'Centre introuvable'
                ]
            ], 404);
        }

        // ── Charger le tenant ──
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TENANT_NOT_FOUND',
                    'message' => 'Centre introuvable'
                ]
            ], 404);
        }

        // ── Mettre le tenant en contexte global ──
        app()->instance('tenant', $tenant);
        $request->merge(['tenant_id' => $tenant->id]);

        // ── Scope global automatique ──
        config(['tenant.current_id' => $tenant->id]);

        return $next($request);
    }
}
