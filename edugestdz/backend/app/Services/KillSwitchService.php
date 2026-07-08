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

    private function executerAction(KillSwitchVote $vote): void
    {
        try {
            Cache::put('kill_switch:active', true, now()->addHour());

            Log::critical('KillSwitch: action executee', [
                'action' => $vote->action,
                'payload' => $vote->payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('KillSwitch: echec execution action', [
                'action' => $vote->action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
