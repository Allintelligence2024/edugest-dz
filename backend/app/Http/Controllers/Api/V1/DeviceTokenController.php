<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\{Request, JsonResponse};

class DeviceTokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'required|in:ios,android,web',
        ]);

        $deviceToken = DeviceToken::firstOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'   => auth()->id(),
                'platform'  => $validated['platform'],
            ]
        );

        if (!$deviceToken->wasRecentlyCreated) {
            $deviceToken->update(['platform' => $validated['platform']]);
            return response()->json(['success' => true, 'message' => 'Token mis à jour', 'data' => $deviceToken]);
        }

        return response()->json(['success' => true, 'message' => 'Token enregistré', 'data' => $deviceToken], 201);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
        ]);

        $deviceToken = DeviceToken::where('user_id', auth()->id())
            ->where('token', $validated['token'])
            ->first();

        if (!$deviceToken) {
            return response()->json(['success' => false, 'message' => 'Token introuvable'], 404);
        }

        $deviceToken->delete();

        return response()->json(['success' => true, 'message' => 'Token désenregistré']);
    }

    public function list(Request $request): JsonResponse
    {
        $tokens = DeviceToken::where('user_id', auth()->id())->get();

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }
}
