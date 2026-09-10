<?php

namespace App\Http\Middleware;

use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;

class LicenceCheck
{
    public function __construct(private LicenceService $licence) {}

    public function handle(Request $request, Closure $next)
    {
        // Uniquement en mode self-hosted single-tenant
        if (config('tenant.mode', 'multi') !== 'single') {
            return $next($request);
        }

        // Exclure le health check (ne pas bloquer le monitoring)
        if ($request->is('api/health')) {
            return $next($request);
        }

        $result = $this->licence->verifier();

        if (!$result['valide']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Licence invalide ou expirée.',
                'code'    => 'LICENCE_EXPIRED',
                'contact' => 'support@edugest.dz | +213 XX XX XX XX',
            ], 402); // Payment Required
        }

        // Ajouter l'avertissement en header si expire bientôt
        $response = $next($request);
        if (isset($result['days_left']) && $result['days_left'] <= 30) {
            $response->headers->set(
                'X-Licence-Warning',
                "Expire dans {$result['days_left']} jours"
            );
        }

        return $response;
    }
}
