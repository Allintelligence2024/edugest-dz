<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Presence, Seance};
use App\Services\QrCodeService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;

class QrCodeController extends Controller
{
    public function __construct(private QrCodeService $qrService) {}

    public function demarrerSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seance_id' => 'required|uuid|exists:seances,id',
        ]);

        $seance = Seance::findOrFail($validated['seance_id']);
        $tenantId = config('tenant.current_id');

        if ($seance->tenant_id !== $tenantId) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Séance inaccessible'],
            ], 403);
        }

        $user = Auth::user();
        $roleNom = $user->role?->nom ?? '';
        $estEnseignant = in_array($roleNom, ['enseignant', 'admin']);

        if (!$estEnseignant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Seuls les enseignants peuvent démarrer une session QR'],
            ], 403);
        }

        $session = $this->qrService->demarrerSession($seance->id, $tenantId);

        return response()->json([
            'success' => true,
            'data'    => $session,
            'message' => 'Session QR démarrée',
        ], 201);
    }

    public function fermerSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seance_id' => 'required|uuid',
        ]);

        $this->qrService->fermerSession($validated['seance_id']);

        return response()->json([
            'success' => true,
            'message' => 'Session QR fermée',
        ]);
    }

    public function scanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token'  => 'required|string',
            'seance_id' => 'required|uuid|exists:seances,id',
            'eleve_id'  => 'required|uuid|exists:eleves,id',
        ]);

        $session = $this->qrService->validerTokenSession(
            $validated['qr_token'],
            $validated['seance_id']
        );

        if (!$session) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRE', 'message' => 'Session QR expirée ou invalide'],
            ], 422);
        }

        $seance = Seance::with('cours')->findOrFail($validated['seance_id']);

        if ($seance->tenant_id !== config('tenant.current_id')) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Séance inaccessible'],
            ], 403);
        }

        $dejaPresent = Presence::where('seance_id', $seance->id)
            ->where('eleve_id', $validated['eleve_id'])
            ->whereIn('statut', ['présent', 'retard'])
            ->exists();

        if ($dejaPresent) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DEJA_POINTE', 'message' => 'Cet élève a déjà pointé sa présence'],
            ], 409);
        }

        $estEnRetard = now()->gt(
            $seance->date_seance->copy()->setTimeFromTimeString($seance->cours->heure_debut)->addMinutes(10)
        );

        $presence = Presence::create([
            'tenant_id' => config('tenant.current_id'),
            'seance_id' => $seance->id,
            'eleve_id'  => $validated['eleve_id'],
            'statut'    => $estEnRetard ? 'retard' : 'présent',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'presence' => $presence,
                'statut'   => $presence->statut,
            ],
            'message' => "Présence enregistrée ({$presence->statut})",
        ], 201);
    }

    public function statutSession(string $seanceId): JsonResponse
    {
        $active = $this->qrService->estSessionActive($seanceId);

        return response()->json([
            'success' => true,
            'data'    => ['active' => $active],
        ]);
    }
}
