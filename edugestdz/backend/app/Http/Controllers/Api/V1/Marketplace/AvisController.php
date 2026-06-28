<?php
namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{Avis, Reservation, Eleve, Enseignant};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;

class AvisController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|uuid|exists:reservations,id',
            'note'           => 'required|integer|min:1|max:5',
            'commentaire'    => 'nullable|string|max:1000',
        ]);

        $reservation = Reservation::findOrFail($validated['reservation_id']);

        if ($reservation->statut !== 'terminee') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_TERMINEE', 'message' => 'Vous ne pouvez laisser un avis que sur une réservation terminée'],
            ], 422);
        }

        $user = Auth::user();
        $eleve = Eleve::where('user_id', $user->id)->first();

        if (!$eleve || $eleve->id !== $reservation->eleve_id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Cette réservation ne vous appartient pas'],
            ], 403);
        }

        $existing = Avis::where('reservation_id', $reservation->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'ALREADY_REVIEWED', 'message' => 'Vous avez déjà laissé un avis pour cette réservation'],
            ], 422);
        }

        $avis = Avis::create([
            'reservation_id' => $reservation->id,
            'eleve_id'       => $eleve->id,
            'enseignant_id'  => $reservation->offre->enseignant_id,
            'note'           => $validated['note'],
            'commentaire'    => $validated['commentaire'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avis créé',
            'data'    => $avis->fresh(),
        ], 201);
    }

    public function byEnseignant(string $enseignantId): JsonResponse
    {
        $avis = Avis::with(['eleve', 'reservation'])
            ->where('enseignant_id', $enseignantId)
            ->orderBy('created_at', 'desc')
            ->get();

        $moyenne = $avis->avg('note');

        return response()->json([
            'success' => true,
            'data'    => [
                'avis'     => $avis,
                'moyenne'  => $moyenne ? round($moyenne, 1) : null,
                'total'    => $avis->count(),
            ],
        ]);
    }
}
