<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MatchingService;
use Illuminate\Http\{Request, JsonResponse};

class MatchingController extends Controller
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id' => 'required|uuid|exists:eleves,id',
            'limit'    => 'sometimes|integer|min:1|max:20',
            'use_llm'  => 'sometimes|boolean',
        ]);

        $limit   = $validated['limit'] ?? 10;
        $useLlm  = $validated['use_llm'] ?? true;

        if (!$useLlm) {
            config(['services.openai.key' => null]);
        }

        $result = $this->matchingService->getSuggestions($validated['eleve_id'], $limit);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'total'    => count($result['data']),
                'llm_used' => $result['llm_used'],
            ],
        ]);
    }
}
