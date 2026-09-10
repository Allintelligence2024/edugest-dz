<?php

namespace App\Http\Middleware;

use App\Services\DeviceFingerprintService;
use App\Services\RiskScoreEngine;
use App\Services\SentryObservabilite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZeroTrustMiddleware
{
    private RiskScoreEngine $riskScore;
    private DeviceFingerprintService $deviceFingerprint;

    public function __construct(RiskScoreEngine $riskScore, DeviceFingerprintService $deviceFingerprint)
    {
        $this->riskScore = $riskScore;
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function handle(Request $request, Closure $next, string $mode = 'normal')
    {
        $user = auth('api')->user();

        if (!$user) {
            return $next($request);
        }

        $score = $this->riskScore->calculerScore($request, $user);

        $request->attributes->set('zero_trust_score', $score);

        Log::info('ZeroTrust: score calculé', [
            'user_id' => $user->id,
            'score' => $score,
            'mode' => $mode,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        // Sprint 6 § 4 : tant que le mode strict n'est branché sur aucune
        // route (pentest § 3, constat C3), le score n'est pas enforcé — le
        // rendre au moins observable. Seuls les scores franchissant le seuil
        // strict (50) sont signalés, pour ne pas noyer les alertes.
        if ($score > 50) {
            SentryObservabilite::capturer(
                "Score de risque Zero-Trust élevé ({$score}/100)",
                'warning',
                [
                    'user_id' => $user->id,
                    'score'   => $score,
                    'mode'    => $mode,
                    'ip'      => $request->ip(),
                    'path'    => $request->path(),
                ]
            );
        }

        if ($mode === 'strict' && $score > 50) {
            $deviceHash = $this->deviceFingerprint->genererEmpreinte($request);
            $challenge = $this->deviceFingerprint->creerChallenge($user);

            return response()->json([
                'success' => false,
                'message' => 'Vérification supplémentaire requise.',
                'code' => 'ZERO_TRUST_CHALLENGE',
                'challenge' => $challenge['challenge'],
                'expires_at' => $challenge['expires_at'],
            ], 428);
        }

        return $next($request);
    }
}
