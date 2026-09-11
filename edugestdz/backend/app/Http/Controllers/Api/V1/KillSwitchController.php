<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\KillSwitchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KillSwitchController extends Controller
{
    private KillSwitchService $killSwitch;

    public function __construct(KillSwitchService $killSwitch)
    {
        $this->killSwitch = $killSwitch;
    }

    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|max:50',
            'payload' => 'sometimes|array',
        ]);

        $vote = $this->killSwitch->initierVote(
            $request->user(),
            $request->input('action'),
            $request->input('payload', [])
        );

        return response()->json([
            'success' => true,
            'vote_id' => $vote->id,
            'expires_at' => $vote->expires_at,
            'message' => 'Vote initie. Un second administrateur doit approuver.',
        ]);
    }

    public function approuver(Request $request, string $voteId): JsonResponse
    {
        $vote = $this->killSwitch->approuverVote($request->user(), $voteId);

        if (!$vote) {
            return response()->json([
                'success' => false,
                'message' => 'Vote expire, deja traite ou invalide.',
                'code' => 'VOTE_INVALID',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vote approuve. Action executee.',
            'vote_id' => $vote->id,
            'status' => $vote->status,
        ]);
    }

    public function refuser(Request $request, string $voteId): JsonResponse
    {
        $vote = $this->killSwitch->refuserVote($request->user(), $voteId);

        if (!$vote) {
            return response()->json([
                'success' => false,
                'message' => 'Vote expire ou deja traite.',
                'code' => 'VOTE_INVALID',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vote refuse.',
            'vote_id' => $vote->id,
        ]);
    }
}
