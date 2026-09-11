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
            $challenge = $this->deviceFingerprint->creerChallenge($user);

            // Pentest Sprint 6 (C3) : l'ancienne réponse renvoyait le code du
            // challenge DANS le corps du 428 — le client recevait lui-même le
            // secret à présenter, le contrôle ne prouvait rien. Le code part
            // désormais hors-bande, par e-mail au titulaire du compte.
            $this->envoyerCodeHorsBande($user, (string) $challenge['challenge']);

            return response()->json([
                'success' => false,
                'message' => 'Nouvel appareil détecté : un code de vérification vous a été envoyé par e-mail.',
                'code' => 'ZERO_TRUST_CHALLENGE',
                'challenge_id' => $challenge['challenge_id'],
                'expires_at' => $challenge['expires_at'],
                'verification_url' => '/api/v1/security/zero-trust/verify',
            ], 428);
        }

        return $next($request);
    }

    /**
     * Livraison du code hors-bande (C3). Jamais bloquant : si l'e-mail
     * échoue, le challenge existe côté serveur et le support peut le
     * relancer — mais on ne rabat JAMAIS le code dans la réponse HTTP.
     */
    private function envoyerCodeHorsBande(\App\Models\User $user, string $code): void
    {
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Votre code de vérification EduGest DZ : {$code}\n\n"
                . 'Il expire dans 15 minutes. Si vous n\'êtes pas à l\'origine de cette connexion, changez votre mot de passe.',
                function (\Illuminate\Mail\Message $message) use ($user) {
                    $message->to($user->email)->subject('EduGest DZ — vérification de nouvel appareil');
                }
            );
        } catch (\Throwable $e) {
            // Ne pas faire échouer la requête pour un échec d'envoi : le
            // challenge reste vérifiable côté serveur.
            Log::warning('ZeroTrust: envoi du code hors-bande échoué', [
                'user_id' => $user->id,
                'erreur'  => $e->getMessage(),
            ]);
        }
    }
}
