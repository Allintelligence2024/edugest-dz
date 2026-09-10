<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiskScoreEngine
{
    private DeviceFingerprintService $deviceFingerprint;

    public function __construct(DeviceFingerprintService $deviceFingerprint)
    {
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function calculerScore(Request $request, User $user): int
    {
        try {
            $score = 0;

            $deviceHash = $this->deviceFingerprint->genererEmpreinte($request);
            $connu = $this->deviceFingerprint->appareilConnu($user, $deviceHash);

            if (!$connu) {
                $score += 40;
            }

            if ($request->header('Sec-Ch-Ua') === null) {
                $score += 15;
            }

            if ($request->header('Accept-Language') === null) {
                $score += 10;
            }

            $heure = now()->hour;
            if ($heure < 6 || $heure > 22) {
                $score += 15;
            }

            $ip = $request->ip();
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $score += 5;
            }

            $proxyHeaders = ['X-Forwarded-For', 'X-Real-IP', 'Via', 'X-Proxy-User-Ip'];
            foreach ($proxyHeaders as $header) {
                if ($request->hasHeader($header)) {
                    $score += 5;
                    break;
                }
            }

            return min(100, $score);

        } catch (\Throwable $e) {
            Log::error('RiskScoreEngine: exception lors du calcul du risque', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return 100;
        }
    }
}
