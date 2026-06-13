<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = app('tenant');

        if ($tenant && $tenant->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'Abonnement expiré. Contactez l\'administrateur.',
                ],
            ], 403);
        }

        if ($tenant && $tenant->date_expiration && now()->gt($tenant->date_expiration)) {
            $tenant->update(['statut' => 'expiré']);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'Abonnement expiré depuis le ' . $tenant->date_expiration->format('d/m/Y'),
                ],
            ], 403);
        }

        return $next($request);
    }
}
