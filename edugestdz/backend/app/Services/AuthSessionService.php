<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthSessionService
{
    /**
     * @return array{payload: array, status: int, cookie?: mixed}
     */
    public function login(Request $request): array
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $monitor = app(\App\Services\SecurityMonitorService::class);

        if ($monitor->estEnBruteForce($credentials['email'], $request->ip())) {
            return [
                'payload' => [
                    'success' => false,
                    'message' => 'Trop de tentatives. Reessayez dans 15 minutes.',
                    'code'    => 'BRUTE_FORCE_BLOCKED',
                ],
                'status'  => 429,
            ];
        }

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            try { $monitor->loginEchoue($credentials['email'], $request->ip()); } catch (\Throwable) {}
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email ou mot de passe incorrect'],
                ],
                'status'  => 401,
            ];
        }

        if (app(TwoFactorService::class)->isLocked($user)) {
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'ACCOUNT_LOCKED', 'message' => 'Compte temporairement verrouillé après trop de tentatives'],
                ],
                'status'  => 423,
            ];
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            app(TwoFactorService::class)->incrementLoginAttempts($user);
            try { $monitor->loginEchoue($credentials['email'], $request->ip()); } catch (\Throwable) {}
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email ou mot de passe incorrect'],
                ],
                'status'  => 401,
            ];
        }

        if ($user->statut !== 'actif') {
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'ACCOUNT_INACTIVE', 'message' => 'Compte désactivé'],
                ],
                'status'  => 403,
            ];
        }

        app(TwoFactorService::class)->resetLoginAttempts($user);

        if ($user->two_factor_confirmed_at !== null) {
            $tempToken = Str::random(60);
            Cache::put('2fa_temp_' . $tempToken, $user->id, now()->addMinutes(5));

            return [
                'payload' => [
                    'success'             => true,
                    'two_factor_required' => true,
                    'two_factor_type'     => $user->two_factor_type,
                    'user_id'             => $user->id,
                    'temp_token'          => $tempToken,
                ],
                'status'  => 200,
            ];
        }

        $token  = JWTAuth::fromUser($user);
        $tenant = $user->tenant;

        // Le refresh token part dans un cookie httpOnly : il n'est jamais
        // exposé au JavaScript, donc invulnérable au vol par XSS.
        [, $cookieRefresh] = app(RefreshTokenService::class)->emettre($user, $request);

        return [
            'payload' => [
                'success'       => true,
                'access_token'  => $token,
                'token_type'    => 'bearer',
                'expires_in'    => auth()->factory()->getTTL() * 60,
                'user'          => $this->formatUser($user),
                'tenant'        => $tenant ? [
                    'id'               => $tenant->id,
                    'nom'              => $tenant->nom_etablissement,
                    'slug'             => $tenant->slug,
                    'statut'           => $tenant->statut,
                    'date_expiration'  => $tenant->date_expiration,
                    'wilaya_id'        => $tenant->wilaya_id,
                    'commune_id'       => $tenant->commune_id,
                    'telephone'        => $tenant->telephone,
                ] : null,
            ],
            'status'  => 200,
            'cookie'  => $cookieRefresh,
        ];
    }

    /**
     * @return array{payload: array, status: int, cookie?: mixed}
     */
    public function complete2fa(Request $request): array
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code'       => 'required|string',
        ]);

        $userId = Cache::pull('2fa_temp_' . $request->temp_token);

        if (!$userId) {
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'INVALID_TEMP_TOKEN', 'message' => 'Session 2FA expirée ou invalide'],
                ],
                'status'  => 422,
            ];
        }

        $user = User::find($userId);

        if (!$user) {
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'USER_NOT_FOUND', 'message' => 'Utilisateur introuvable'],
                ],
                'status'  => 404,
            ];
        }

        $twoFactorService = app(TwoFactorService::class);
        $valid = false;

        if ($user->two_factor_type === 'totp') {
            $valid = $twoFactorService->verifyCode($user->two_factor_secret, $request->code);

            if (!$valid) {
                $valid = $twoFactorService->validateRecoveryCode($request->code, $user);
            }
        } elseif ($user->two_factor_type === 'sms') {
            $valid = $twoFactorService->verifySmsOtp($user, $request->code);
        }

        if (!$valid) {
            return [
                'payload' => [
                    'success' => false,
                    'error'   => ['code' => 'INVALID_2FA_CODE', 'message' => 'Code 2FA invalide'],
                ],
                'status'  => 422,
            ];
        }

        $token  = JWTAuth::fromUser($user);
        $tenant = $user->tenant;

        [, $cookieRefresh] = app(RefreshTokenService::class)->emettre($user, $request);

        return [
            'payload' => [
                'success'       => true,
                'access_token'  => $token,
                'token_type'    => 'bearer',
                'expires_in'    => auth()->factory()->getTTL() * 60,
                'user'          => $this->formatUser($user),
                'tenant'        => $tenant ? [
                    'id'               => $tenant->id,
                    'nom'              => $tenant->nom_etablissement,
                    'slug'             => $tenant->slug,
                    'statut'           => $tenant->statut,
                    'date_expiration'  => $tenant->date_expiration,
                    'wilaya_id'        => $tenant->wilaya_id,
                    'commune_id'       => $tenant->commune_id,
                    'telephone'        => $tenant->telephone,
                ] : null,
            ],
            'status'  => 200,
            'cookie'  => $cookieRefresh,
        ];
    }

    /**
     * @return array{payload: array, status: int, cookie?: mixed}
     */
    public function logout(Request $request): array
    {
        $service = app(RefreshTokenService::class);

        // Un logout ne doit jamais renvoyer 500 : si le JWT est absent ou
        // illisible, on révoque quand même ce qu'on peut et on efface le
        // cookie. Se déconnecter doit toujours aboutir.
        try {
            $userId = auth('api')->id();
        } catch (\Throwable) {
            $userId = null;
        }

        // Révoquer le refresh token présenté, et par sécurité toutes les
        // sessions de l'utilisateur : un logout doit être sans ambiguïté.
        if ($cookie = $request->cookie(RefreshTokenService::COOKIE)) {
            $service->revoquerJeton((string) $cookie, 'logout');
        }

        if ($userId) {
            $service->revoquerUtilisateur($userId, 'logout');
        }

        try {
            auth('api')->logout();
        } catch (\Throwable) {
            // Jeton déjà invalide : la déconnexion est de fait effective.
        }

        return [
            'payload' => ['success' => true, 'message' => 'Déconnexion réussie'],
            'status'  => 200,
            'cookie'  => $service->cookieEfface(),
        ];
    }

    /**
     * @return array{payload: array, status: int, cookie?: mixed}
     */
    public function refresh(Request $request): array
    {
        $service = app(RefreshTokenService::class);

        // Le refresh token vient du cookie httpOnly. On accepte encore le
        // corps de requête pour les clients mobiles, qui utilisent un
        // stockage sécurisé natif (Expo SecureStore) et non un navigateur.
        // Lecture défensive : selon la pile de middlewares traversée, un
        // cookie non chiffré peut n'être visible que dans le sac Symfony et
        // pas via $request->cookie(), qui suppose un cookie géré par Laravel.
        // On tente les trois sources plutôt que d'échouer silencieusement.
        $presente = $request->cookie(RefreshTokenService::COOKIE)
            ?: $request->cookies->get(RefreshTokenService::COOKIE)
            ?: $request->input('refresh_token');

        if ($presente) {
            $rotation = $service->faireTourner((string) $presente, $request);

            if ($rotation === null) {
                return [
                    'payload' => [
                        'success' => false,
                        'error'   => ['code' => 'REFRESH_INVALIDE', 'message' => 'Session expirée, veuillez vous reconnecter'],
                    ],
                    'status'  => 401,
                    'cookie'  => $service->cookieEfface(),
                ];
            }

            [$user, $nouveauClair, $cookie] = $rotation;

            $reponse = [
                'success'      => true,
                'access_token' => JWTAuth::fromUser($user),
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ];

            // Les clients non navigateur ont besoin du jeton en clair ;
            // les navigateurs se contentent du cookie.
            if (!$request->cookie(RefreshTokenService::COOKIE)) {
                $reponse['refresh_token'] = $nouveauClair;
            }

            return ['payload' => $reponse, 'status' => 200, 'cookie' => $cookie];
        }

        // Repli : rafraîchissement à partir du JWT encore valide.
        //
        // Cette route étant devenue publique, il faut viser explicitement le
        // guard `api` : sans utilisateur résolu par un middleware, auth() sans
        // argument ne rattacherait aucun jeton et le refresh échouerait pour
        // les clients qui n'ont pas encore de cookie (mobile, tests).
        try {
            $token = auth('api')->refresh();
        } catch (\Throwable) {
            return [
                'payload' => ['success' => false, 'error' => ['code' => 'TOKEN_EXPIRED', 'message' => 'Token expiré, veuillez vous reconnecter']],
                'status'  => 401,
            ];
        }

        return [
            'payload' => [
                'success'      => true,
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ],
            'status'  => 200,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatUser(User $u): array
    {
        return [
            'id'                 => $u->id,
            'nom'                => $u->nom,
            'prenom'             => $u->prenom,
            'email'              => $u->email,
            'telephone'          => $u->telephone,
            'role'               => $u->role?->nom,
            'langue'             => $u->langue,
            'two_factor_enabled' => $u->two_factor_confirmed_at !== null,
            'two_factor_type'    => $u->two_factor_type,
        ];
    }
}
