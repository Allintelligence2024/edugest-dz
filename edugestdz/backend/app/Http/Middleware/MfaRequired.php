<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MfaRequired
{
    private const ROLES_REQUIERANT_MFA = ['admin', 'super_admin'];

    private const ROUTES_EXCLUES = [
        'api/v1/auth/logout',
        'api/v1/auth/2fa/enable',
        'api/v1/auth/2fa/verify',
        'api/v1/auth/2fa/setup',
        'api/v1/auth/me',
        'api/health',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if (!$user) return $next($request);

        $roleNom = is_string($user->role) ? $user->role : ($user->role?->nom ?? '');
        if (!in_array($roleNom, self::ROLES_REQUIERANT_MFA)) {
            return $next($request);
        }

        foreach (self::ROUTES_EXCLUES as $route) {
            if ($request->is($route)) return $next($request);
        }

        $mfaActive = $user->two_factor_secret !== null
            || $user->google2fa_secret !== null
            || ($user->mfa_actif ?? false);

        if (!$mfaActive) {
            \Illuminate\Support\Facades\Log::warning('MFA_REQUIRED: admin sans 2FA tente d\'acceder a l\'API', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'role'    => $user->role?->nom,
                'ip'      => $request->ip(),
                'path'    => $request->path(),
            ]);

            return response()->json([
                'success'      => false,
                'message'      => 'La double authentification (2FA) est obligatoire pour les administrateurs.',
                'code'         => 'MFA_REQUIRED',
                'instructions' => 'Activez la 2FA depuis Parametres → Securite → Activer 2FA.',
                'setup_url'    => '/api/v1/auth/2fa/setup',
            ], 403);
        }

        return $next($request);
    }
}
