<?php

namespace App\Services;

use App\Models\DeviceChallenge;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceFingerprintService
{
    public function genererEmpreinte(Request $request): string
    {
        $parts = [
            $request->header('User-Agent', ''),
            $request->header('Accept-Language', ''),
            $request->header('Sec-Ch-Ua', ''),
            $request->header('Sec-Ch-Ua-Platform', ''),
            $request->header('Sec-Ch-Ua-Mobile', ''),
            $request->ip(),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public function appareilConnu(User $user, string $deviceHash): bool
    {
        return TrustedDevice::where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->exists();
    }

    public function marquerConnu(User $user, string $deviceHash, ?string $deviceName = null, ?string $ip = null, ?string $userAgent = null): TrustedDevice
    {
        return TrustedDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_hash' => $deviceHash,
            ],
            [
                'device_name' => $deviceName,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'last_used_at' => now(),
                'trusted_at' => now(),
            ]
        );
    }

    public function creerChallenge(User $user): array
    {
        $rawCode = Str::random(64);
        $hash = hash('sha256', $rawCode);

        DeviceChallenge::create([
            'user_id' => $user->id,
            'challenge_hash' => $hash,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(15),
        ]);

        return [
            'challenge' => $rawCode,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function verifierChallenge(User $user, string $rawCode): bool
    {
        $hash = hash('sha256', $rawCode);

        $challenge = DeviceChallenge::where('user_id', $user->id)
            ->where('challenge_hash', $hash)
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($challenge) {
            $challenge->increment('attempts');

            if ($challenge->tropDeTentatives()) {
                $challenge->invalider();
                return false;
            }

            $challenge->invalider();
            return true;
        }

        $latest = DeviceChallenge::where('user_id', $user->id)
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($latest) {
            $latest->increment('attempts');

            if ($latest->tropDeTentatives()) {
                $latest->invalider();
            }
        }

        return false;
    }
}
