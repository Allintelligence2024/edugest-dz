<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthCompteService
{
    public function __construct(private AuthSessionService $session) {}

    /**
     * @return array{payload: array, status: int}
     */
    public function me(): array
    {
        $user = auth()->user();

        return [
            'payload' => [
                'success' => true,
                'data'    => $this->session->formatUser($user),
                'tenant'  => $user->tenant ? [
                    'id'               => $user->tenant->id,
                    'nom'              => $user->tenant->nom_etablissement,
                    'slug'             => $user->tenant->slug,
                    'statut'           => $user->tenant->statut,
                ] : null,
            ],
            'status'  => 200,
        ];
    }

    /**
     * @return array{payload: array, status: int}
     */
    public function changePassword(Request $request): array
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $policyService = app(\App\Services\PasswordPolicyService::class);
        $violations    = $policyService->valider($request->new_password, auth()->user()->email);

        if (!empty($violations)) {
            return [
                'payload' => [
                    'success'    => false,
                    'message'    => 'Mot de passe non conforme à la politique de sécurité.',
                    'violations' => $violations,
                ],
                'status'  => 422,
            ];
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return [
                'payload' => ['success' => false, 'error' => ['code' => 'WRONG_PASSWORD', 'message' => 'Mot de passe actuel incorrect']],
                'status'  => 422,
            ];
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return [
            'payload' => ['success' => true, 'message' => 'Mot de passe modifié avec succès'],
            'status'  => 200,
        ];
    }

    /**
     * @return array{payload: array, status: int}
     */
    public function updateProfile(Request $request): array
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nom'         => 'sometimes|string|max:100',
            'prenom'      => 'sometimes|string|max:100',
            'telephone'   => 'sometimes|string|max:20',
            'langue'      => 'sometimes|in:fr,ar',
        ]);

        $user->update($validated);

        return [
            'payload' => [
                'success' => true,
                'message' => 'Profil mis à jour',
                'data'    => $this->session->formatUser($user->fresh()),
            ],
            'status'  => 200,
        ];
    }

    /**
     * @return array{payload: array, status: int}
     */
    public function forgotPassword(Request $request): array
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? ['payload' => ['success' => true, 'message' => 'Email de réinitialisation envoyé'], 'status' => 200]
            : ['payload' => ['success' => false, 'error' => ['code' => 'RESET_FAILED', 'message' => 'Impossible d\'envoyer l\'email']], 'status' => 400];
    }

    /**
     * @return array{payload: array, status: int}
     */
    public function resetPassword(Request $request): array
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
            ? ['payload' => ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès'], 'status' => 200]
            : ['payload' => ['success' => false, 'error' => ['code' => 'RESET_FAILED', 'message' => __($status)]], 'status' => 400];
    }
}
