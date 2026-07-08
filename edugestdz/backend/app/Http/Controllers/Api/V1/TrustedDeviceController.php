<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = TrustedDevice::where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'device_name', 'ip_address', 'last_used_at', 'trusted_at', 'created_at']);

        return response()->json($devices);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $device = TrustedDevice::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Appareil retiré de la liste de confiance.',
        ]);
    }
}
