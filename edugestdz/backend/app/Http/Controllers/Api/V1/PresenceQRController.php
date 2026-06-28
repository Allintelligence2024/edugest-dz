<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Eleve, Presence, Seance, Cours};
use App\Services\EleveService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PresenceQRController extends Controller
{
    public function __construct(private EleveService $eleveService) {}

    public function qrcode(string $id)
    {
        $eleve = Eleve::findOrFail($id);

        $token = $this->eleveService->genererTokenQR($eleve);

        $qr = \QrCode::format('png')
            ->size(400)
            ->margin(2)
            ->color(30, 94, 188)
            ->generate(json_encode([
                'token'  => $token,
                'eleve'  => $eleve->id,
                'tenant' => $eleve->tenant_id,
                'nom'    => "{$eleve->nom} {$eleve->prenom}",
            ]));

        return response($qr, 200)->header('Content-Type', 'image/png');
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token'  => 'required|string',
            'seance_id' => 'required|uuid|exists:seances,id',
        ]);

        $payload = $this->eleveService->verifierTokenQR($validated['qr_token']);

        if (!$payload || !isset($payload['eleve'])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'QR_INVALIDE', 'message' => 'QR code invalide ou expiré'],
            ], 422);
        }

        $eleve = Eleve::find($payload['eleve']);
        if (!$eleve || $eleve->tenant_id !== config('tenant.current_id')) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'QR_INVALIDE', 'message' => 'QR code invalide pour cet établissement'],
            ], 422);
        }

        $seance = Seance::with('cours')->findOrFail($validated['seance_id']);

        if ($seance->tenant_id !== config('tenant.current_id')) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Séance inaccessible']], 403);
        }

        $dejaPresent = Presence::where('seance_id', $seance->id)
            ->where('eleve_id', $eleve->id)
            ->whereIn('statut', ['present', 'retard'])
            ->exists();

        if ($dejaPresent) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DEJA_POINTE', 'message' => 'Cet élève a déjà pointé sa présence'],
            ], 409);
        }

        $estEnRetard = now()->gt($seance->date_seance->copy()->setTimeFromTimeString($seance->cours->heure_debut)->addMinutes(10));

        $presence = Presence::create([
            'tenant_id'   => config('tenant.current_id'),
            'seance_id'   => $seance->id,
            'eleve_id'    => $eleve->id,
            'statut'      => $estEnRetard ? 'retard' : 'present',
            'date_pointe' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'presence'  => $presence,
                'eleve'     => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
                'statut'    => $presence->statut,
                'seance'    => "{$seance->cours->groupe->nom} - " . $seance->date_seance->format('d/m/Y'),
            ],
            'message' => "Présence enregistrée : {$eleve->prenom} {$eleve->nom} ({$presence->statut})",
        ], 201);
    }
}
