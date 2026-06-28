<?php
namespace App\Services;

use App\Models\User;
use App\Services\Sms\TwilioSmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwoFactorService
{
    public function generateSecret(): array
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }

        $issuer = config('app.name', 'EduGest DZ');
        $email = auth()->user()?->email ?? 'user@edugestdz.local';
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );

        return [
            'secret'      => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $expected = $this->generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateTOTP(string $secret, int $timeSlice): string
    {
        $decoded = $this->base32Decode($secret);
        $counter = pack('NN', 0, $timeSlice);
        $hash = hash_hmac('sha1', $counter, $decoded, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(str_replace('=', '', $input));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $output .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
                $bitsLeft -= 8;
            }
        }

        return $output;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(10));
        }
        return $codes;
    }

    public function validateRecoveryCode(string $code, User $user): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode($user->two_factor_recovery_codes, true);

        if (!is_array($codes)) {
            return false;
        }

        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->update([
            'two_factor_recovery_codes' => json_encode(array_values($codes)),
        ]);

        return true;
    }

    public function sendSmsOtp(User $user): bool
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashedOtp = Hash::make($otp);

        Cache::put('2fa_sms_otp_' . $user->id, $hashedOtp, now()->addMinutes(5));

        $phone = $user->two_factor_phone ?? $user->telephone;

        if (!$phone) {
            Log::warning('[2FA] Aucun numéro de téléphone pour SMS OTP', ['user_id' => $user->id]);
            return false;
        }

        try {
            $smsService = app(TwilioSmsService::class);
            $result = $smsService->send($phone, __('Votre code de vérification EduGest DZ est : :code', ['code' => $otp]));

            if ($result['success']) {
                Log::info('[2FA] SMS OTP envoyé', ['user_id' => $user->id, 'to' => $phone]);
                return true;
            }

            Log::warning('[2FA] Échec envoi SMS OTP', ['user_id' => $user->id, 'error' => $result['error'] ?? 'unknown']);
            return false;
        } catch (\Throwable $e) {
            Log::warning('[2FA] Exception envoi SMS OTP', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function verifySmsOtp(User $user, string $code): bool
    {
        $hashedOtp = Cache::get('2fa_sms_otp_' . $user->id);

        if (!$hashedOtp) {
            return false;
        }

        if (!Hash::check($code, $hashedOtp)) {
            return false;
        }

        Cache::forget('2fa_sms_otp_' . $user->id);
        return true;
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_until !== null && now()->lessThan($user->locked_until);
    }

    public function incrementLoginAttempts(User $user): void
    {
        $attempts = $user->login_attempts + 1;

        if ($attempts >= 5) {
            $user->update([
                'login_attempts' => $attempts,
                'locked_until'   => now()->addMinutes(15),
            ]);
        } else {
            $user->update(['login_attempts' => $attempts]);
        }
    }

    public function resetLoginAttempts(User $user): void
    {
        $user->update([
            'login_attempts' => 0,
            'locked_until'   => null,
        ]);
    }
}
