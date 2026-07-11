<?php

namespace App\Services;

use App\Models\KillSwitchVote;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KillSwitchService
{
    private const FENETRE_VOTE_SEC = 600;
    private const CACHE_VOTE_PREFIX = 'kill_switch_vote:';

    public function initierVote(User $initiator, string $action, array $payload = []): KillSwitchVote
    {
        $voteExistant = $this->voteEnCours($initiator, $action);

        if ($voteExistant) {
            return $voteExistant;
        }

        $vote = KillSwitchVote::create([
            'initiator_id' => $initiator->id,
            'approver_id' => null,
            'action' => $action,
            'payload' => $payload,
            'expires_at' => now()->addSeconds(self::FENETRE_VOTE_SEC),
            'status' => 'pending',
        ]);

        Cache::put(
            self::CACHE_VOTE_PREFIX . $initiator->id . ':' . $action,
            $vote->id,
            self::FENETRE_VOTE_SEC
        );

        Log::warning('KillSwitch: vote initie', [
            'vote_id' => $vote->id,
            'initiator' => $initiator->id,
            'action' => $action,
            'expires_at' => $vote->expires_at,
        ]);

        return $vote;
    }

    public function approuverVote(User $approver, string $voteId): ?KillSwitchVote
    {
        $vote = KillSwitchVote::findOrFail($voteId);

        if ($vote->estExpire() || $vote->status !== 'pending') {
            return null;
        }

        if ($vote->initiator_id === $approver->id) {
            return null;
        }

        $vote->update([
            'approver_id' => $approver->id,
            'status' => 'approved',
        ]);

        Log::critical('KillSwitch: vote approuve — action declenchee', [
            'vote_id' => $vote->id,
            'action' => $vote->action,
            'initiator' => $vote->initiator_id,
            'approver' => $approver->id,
        ]);

        Cache::forget(self::CACHE_VOTE_PREFIX . $vote->initiator_id . ':' . $vote->action);

        $this->executerAction($vote);

        return $vote;
    }

    public function refuserVote(User $refuser, string $voteId): ?KillSwitchVote
    {
        $vote = KillSwitchVote::findOrFail($voteId);

        if ($vote->status !== 'pending' || $vote->estExpire()) {
            return null;
        }

        $vote->update([
            'approver_id' => $refuser->id,
            'status' => 'rejected',
        ]);

        Log::warning('KillSwitch: vote refuse', [
            'vote_id' => $vote->id,
            'action' => $vote->action,
            'refuser' => $refuser->id,
        ]);

        Cache::forget(self::CACHE_VOTE_PREFIX . $vote->initiator_id . ':' . $vote->action);

        return $vote;
    }

    public function voteEnCours(User $initiator, string $action): ?KillSwitchVote
    {
        $cacheKey = self::CACHE_VOTE_PREFIX . $initiator->id . ':' . $action;
        $voteId = Cache::get($cacheKey);

        if ($voteId) {
            $vote = KillSwitchVote::find($voteId);
            if ($vote && !$vote->estExpire() && $vote->status === 'pending') {
                return $vote;
            }
            Cache::forget($cacheKey);
        }

        return KillSwitchVote::where('initiator_id', $initiator->id)
            ->where('action', $action)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Exécuter l'action du KillSwitch avec persistance double (Redis + BDD).
     * Si Redis est down → la BDD prend le relais (fail-safe).
     */
    private function executerAction(KillSwitchVote $vote): void
    {
        try {
            // ── Persistance Redis (principal) ─────────────────────────────
            Cache::put('kill_switch:active', [
                'reason'       => $vote->action,
                'activated_at' => now()->toIso8601String(),
                'vote_id'      => $vote->id,
            ], now()->addHours(24)); // 24h max — doit être désactivé manuellement

            // ── Persistance BDD (fallback si Redis down) ──────────────────
            \DB::table('kill_switch_state')
                ->update([
                    'is_active'    => true,
                    'reason'       => $vote->action,
                    'activated_by' => $vote->initiator_id,
                    'approved_by'  => $vote->approver_id,
                    'activated_at' => now(),
                    'deactivated_at' => null,
                    'updated_at'   => now(),
                ]);

            Log::critical('KillSwitch: action exécutée — persistance Redis + BDD', [
                'action'  => $vote->action,
                'vote_id' => $vote->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('KillSwitch: échec activation', [
                'action' => $vote->action,
                'error'  => $e->getMessage(),
            ]);
            // Re-lancer pour que l'appelant sache que ça a échoué
            throw $e;
        }
    }

    /**
     * Vérifier si le KillSwitch est actif.
     *
     * Stratégie :
     * 1. Redis disponible → répondre depuis Redis uniquement (rapide, sans BDD)
     * 2. Redis down → fallback sur BDD kill_switch_state
     * 3. Les deux down → fail-open (laisser passer)
     */
    public function estActif(): bool
    {
        // ── Vérification Redis (principale) ───────────────────────────────
        try {
            // Cache::has() lève une exception si Redis est down
            // Sinon retourne true/false immédiatement (sans BDD)
            return Cache::has('kill_switch:active');
        } catch (\Throwable) {
            // Redis indisponible → fallback BDD
            Log::warning(
                'KillSwitch: Redis indisponible — fallback BDD'
            );
        }

        // ── Fallback BDD (seulement si Redis down) ─────────────────────────
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('kill_switch_state')) {
                return false;
            }
            return (bool) \DB::table('kill_switch_state')
                ->where('is_active', true)
                ->whereNull('deactivated_at')
                ->exists();
        } catch (\Throwable $e) {
            Log::error(
                'KillSwitch: impossible de vérifier (Redis + BDD down) — LAISSER PASSER',
                ['error' => $e->getMessage()]
            );
            return false; // fail-open intentionnel
        }
    }

    /**
     * Désactiver le KillSwitch (Redis + BDD).
     */
    public function desactiver(string $adminId): void
    {
        // ── Désactiver dans Redis ──────────────────────────────────────────
        try {
            Cache::forget('kill_switch:active');
        } catch (\Throwable) {}

        // ── Désactiver dans BDD ───────────────────────────────────────────
        try {
            \DB::table('kill_switch_state')
                ->update([
                    'is_active'      => false,
                    'deactivated_at' => now(),
                    'updated_at'     => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('KillSwitch: impossible de désactiver en BDD', ['error' => $e->getMessage()]);
        }

        Log::warning('KillSwitch: désactivé', ['admin' => $adminId]);
    }
}
