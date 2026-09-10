<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\DeviceFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedDeviceController extends Controller
{
    public function __construct(private DeviceFingerprintService $fingerprints) {}

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

    /**
     * Sprint 6 (pentest C3) — consommer un challenge Zero-Trust.
     *
     * Jusqu'ici, verifierChallenge() du service n'avait AUCUNE route
     * consommatrice : le flux 428 → vérification était inachevé de bout en
     * bout. Le code est reçu hors-bande (e-mail, cf. ZeroTrustMiddleware) ;
     * une vérification réussie déclare l'appareil courant comme confiance.
     */
    public function verifierChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:128',
        ]);

        $user = $request->user();

        if (!$this->fingerprints->verifierChallenge($user, (string) $validated['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide, expiré ou nombre de tentatives dépassé.',
                'code'    => 'CHALLENGE_INVALIDE',
            ], 422);
        }

        $this->fingerprints->marquerConnu(
            $user,
            $this->fingerprints->genererEmpreinte($request),
            null,
            $request->ip(),
            substr($request->userAgent() ?? '', 0, 200)
        );

        return response()->json([
            'success' => true,
            'message' => 'Appareil vérifié et ajouté à vos appareils de confiance.',
        ]);
    }
}
