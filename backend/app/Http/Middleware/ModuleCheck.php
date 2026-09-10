<?php

namespace App\Http\Middleware;

use App\Models\TenantModule;
use Closure;
use Illuminate\Http\Request;

class ModuleCheck
{
    private const ROUTE_MAP = [
        'v1/transport'    => 'transport',
        'v1/cantine'      => 'cantine',
        'v1/stock'        => 'stock',
        'v1/budget'       => 'budget',
        'v1/personnel'    => 'personnel',
        'v1/entretien'    => 'entretien',
        'v1/surveillance' => 'surveillance',
        'v1/lms'          => 'lms',
        'v1/marketplace'  => 'marketplace',
        'v1/centres'      => 'marketplace',
        'v1/examens'      => 'examens',
        'v1/diagnostic'   => 'diagnostic',
        'v1/billets'      => 'billets',
        'v1/pointage'     => 'pointage',
        'v1/bibliotheque' => 'bibliotheque',
    ];

    public function handle(Request $request, Closure $next, ?string $moduleKey = null)
    {
        $tenantId = config('tenant.current_id');
        if (!$tenantId) return $next($request);

        if ($request->is('api/health')) return $next($request);

        $module = $moduleKey ?? $this->detecterModuleDepuisRoute($request->path());

        if (!$module) return $next($request);

        if (!TenantModule::estActif($tenantId, $module)) {
            return response()->json([
                'success' => false,
                'message' => "Le module « {$module} » n'est pas activé pour votre établissement.",
                'code'    => 'MODULE_DISABLED',
                'module'  => $module,
                'action'  => 'Contactez votre administrateur ou activez le module dans Paramètres → Modules.',
            ], 403);
        }

        return $next($request);
    }

    private function detecterModuleDepuisRoute(string $path): ?string
    {
        foreach (self::ROUTE_MAP as $prefix => $module) {
            if (str_starts_with($path, $prefix)) {
                return $module;
            }
        }
        return null;
    }
}
