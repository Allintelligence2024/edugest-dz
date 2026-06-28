<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    public function status(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'enabled' => $user->two_factor_confirmed_at !== null,
                'type'    => $user->two_factor_type,
            ],
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:totp,sms',
        ]);

        $user = auth()->user();
        $type = $request->type;

        if ($user->two_factor_confirmed_at !== null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => '2FA_ALREADY_ENABLED', 'message' => 'La 2FA est déjà activée'],
            ], 422);
        }

        if ($type === 'totp') {
            $secret = $this->twoFactorService->generateSecret();

            return response()->json([
                'success' => true,
                'data'    => [
                    'type'           => 'totp',
                    'secret'         => $secret['secret'],
                    'qr_code_url'    => $secret['qr_code_url'],
                    'recovery_codes' => $this->twoFactorService->generateRecoveryCodes(),
                ],
            ]);
        }

        if ($type === 'sms') {
            $phone = $request->input('phone', $user->telephone);

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'PHONE_REQUIRED', 'message' => 'Numéro de téléphone requis'],
                ], 422);
            }

            $user->update(['two_factor_phone' => $phone]);
            $sent = $this->twoFactorService->sendSmsOtp($user);

            if (!$sent) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'SMS_FAILED', 'message' => 'Impossible d\'envoyer le code SMS'],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'type'    => 'sms',
                    'message' => 'Code de vérification envoyé par SMS',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'error'   => ['code' => 'INVALID_TYPE', 'message' => 'Type 2FA invalide'],
        ], 422);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = auth()->user();
        $code = $request->code;

        if ($user->two_factor_confirmed_at !== null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => '2FA_ALREADY_CONFIRMED', 'message' => 'La 2FA est déjà confirmée'],
            ], 422);
        }

        $valid = false;

        if ($user->two_factor_type === 'totp') {
            if (!$user->two_factor_secret) {
                $valid = false;
            } else {
                $valid = $this->twoFactorService->verifyCode($user->two_factor_secret, $code);
            }
        } elseif ($user->two_factor_type === 'sms') {
            $valid = $this->twoFactorService->verifySmsOtp($user, $code);
        }

        if (!$valid) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_CODE', 'message' => 'Code de vérification invalide'],
            ], 422);
        }

        $update = [
            'two_factor_confirmed_at' => now(),
        ];

        if ($user->two_factor_type === 'totp' && !$user->two_factor_recovery_codes) {
            $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
            $update['two_factor_recovery_codes'] = json_encode($recoveryCodes);
        }

        $user->update($update);

        return response()->json([
            'success' => true,
            'message' => 'Authentification à deux facteurs activée avec succès',
            'data'    => [
                'two_factor_type' => $user->two_factor_type,
            ],
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'WRONG_PASSWORD', 'message' => 'Mot de passe incorrect'],
            ], 422);
        }

        $user->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_type'           => null,
            'two_factor_phone'          => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authentification à deux facteurs désactivée',
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'code'    => 'required|string',
        ]);

        $user = User::find($request->user_id);

        if (!$user || $user->two_factor_confirmed_at === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_USER', 'message' => 'Utilisateur invalide ou 2FA non activée'],
            ], 422);
        }

        $valid = false;
        $usedRecovery = false;

        if ($user->two_factor_type === 'totp') {
            $valid = $this->twoFactorService->verifyCode($user->two_factor_secret, $request->code);

            if (!$valid) {
                $valid = $this->twoFactorService->validateRecoveryCode($request->code, $user);
                $usedRecovery = $valid;
            }
        } elseif ($user->two_factor_type === 'sms') {
            $valid = $this->twoFactorService->verifySmsOtp($user, $request->code);
        }

        if (!$valid) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_2FA_CODE', 'message' => 'Code 2FA invalide'],
            ], 422);
        }

        $recoveryCodes = $user->fresh()->two_factor_recovery_codes;
        $remaining = $recoveryCodes ? count(json_decode($recoveryCodes, true)) : 0;

        return response()->json([
            'success'                   => true,
            'verified'                  => true,
            'recovery_codes_remaining'  => $remaining,
        ]);
    }

    public function recoveryCodes(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->two_factor_recovery_codes) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NO_RECOVERY_CODES', 'message' => 'Aucun code de récupération'],
            ], 404);
        }

        $codes = json_decode($user->two_factor_recovery_codes, true);

        $masked = array_map(fn(string $code) => substr($code, 0, 4) . '******', $codes);

        return response()->json([
            'success' => true,
            'data'    => [
                'codes'     => $masked,
                'remaining' => count($codes),
            ],
        ]);
    }
}
