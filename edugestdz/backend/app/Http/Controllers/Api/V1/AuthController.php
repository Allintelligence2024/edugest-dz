<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Hash, Password};
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email ou mot de passe incorrect'],
            ], 401);
        }

        $user  = auth()->user();
        $tenant = $user->tenant;

        return response()->json([
            'success'       => true,
            'access_token'  => $token,
            'token_type'    => 'bearer',
            'expires_in'    => auth()->factory()->getTTL() * 60,
            'user'          => $this->formatUser($user),
            'tenant'        => $tenant ? [
                'id'               => $tenant->id,
                'nom'              => $tenant->nom,
                'slug'             => $tenant->slug,
                'statut'           => $tenant->statut,
                'date_expiration'  => $tenant->date_expiration,
                'wilaya_id'        => $tenant->wilaya_id,
                'commune_id'       => $tenant->commune_id,
                'telephone'        => $tenant->telephone,
            ] : null,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->logout();
        return response()->json(['success' => true, 'message' => 'Déconnexion réussie']);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = auth()->refresh();
        } catch (\Exception) {
            return response()->json(['success' => false, 'error' => ['code' => 'TOKEN_EXPIRED', 'message' => 'Token expiré, veuillez vous reconnecter']], 401);
        }

        return response()->json([
            'success'      => true,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth()->factory()->getTTL() * 60,
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
            'tenant'  => $user->tenant ? [
                'id'               => $user->tenant->id,
                'nom'              => $user->tenant->nom,
                'slug'             => $user->tenant->slug,
                'statut'           => $user->tenant->statut,
            ] : null,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'error' => ['code' => 'WRONG_PASSWORD', 'message' => 'Mot de passe actuel incorrect']], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nom'         => 'sometimes|string|max:100',
            'prenom'      => 'sometimes|string|max:100',
            'telephone'   => 'sometimes|string|max:20',
            'langue'      => 'sometimes|in:fr,ar',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour',
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['success' => true, 'message' => 'Email de réinitialisation envoyé'])
            : response()->json(['success' => false, 'error' => ['code' => 'RESET_FAILED', 'message' => 'Impossible d\'envoyer l\'email']], 400);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn(User $user, string $password) => $user->update(['password' => Hash::make($password)])
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès'])
            : response()->json(['success' => false, 'error' => ['code' => 'RESET_FAILED', 'message' => __($status)]], 400);
    }

    private function formatUser(User $u): array
    {
        return [
            'id'        => $u->id,
            'nom'       => $u->nom,
            'prenom'    => $u->prenom,
            'email'     => $u->email,
            'telephone' => $u->telephone,
            'role'      => $u->getRoleNames()->first(),
            'langue'    => $u->langue,
        ];
    }
}
