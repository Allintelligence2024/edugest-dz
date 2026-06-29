<?php
namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{Reservation, OffrePublique, Enseignant, Eleve, Tenant, Facture};
use App\Services\Marketplace\{CommissionService, VisioService};
use App\Http\Controllers\Api\V1\PaiementEnLigneController;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function __construct(
        private CommissionService $commissionService,
        private VisioService $visioService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offre_id'  => 'required|uuid|exists:offres_publiques,id',
            'message'   => 'nullable|string|max:500',
            'date_debut' => 'required|date|after_or_equal:today',
        ]);

        $offre = OffrePublique::findOrFail($validated['offre_id']);

        if ($offre->statut !== 'active') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'OFFRE_INACTIVE', 'message' => 'Cette offre n\'est plus active'],
            ], 422);
        }

        if ($offre->places_restantes < 1) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'COMPLET', 'message' => 'Cette offre n\'a plus de places disponibles'],
            ], 422);
        }

        $user = Auth::user();
        $eleve = Eleve::where('user_id', $user->id)->first();

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NO_ELEVE', 'message' => 'Vous devez être un parent avec un élève pour réserver'],
            ], 403);
        }

        $montant = $offre->tarif_seance;
        $tenant = Tenant::find(config('tenant.current_id'));
        $commission = $this->commissionService->calculateCommission($montant, $tenant);

        $reservation = Reservation::create([
            'offre_id'   => $offre->id,
            'eleve_id'   => $eleve->id,
            'statut'     => 'en_attente',
            'montant'    => $montant,
            'commission' => $commission,
            'message'    => $validated['message'] ?? null,
            'date_debut' => $validated['date_debut'],
        ]);

        $offre->decrement('places_restantes');

        return response()->json([
            'success' => true,
            'message' => 'Réservation créée',
            'data'    => $reservation->fresh()->load(['offre.matiere', 'offre.enseignant.user']),
        ], 201);
    }

    public function payer(Request $request, string $id): JsonResponse
    {
        $reservation = Reservation::with('offre')->findOrFail($id);

        $user = Auth::user();
        $eleve = Eleve::where('user_id', $user->id)->first();

        if (!$eleve || $eleve->id !== $reservation->eleve_id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Cette réservation ne vous appartient pas'],
            ], 403);
        }

        if (!in_array($reservation->statut, ['en_attente', 'confirmee'])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_STATUT', 'message' => 'Cette réservation ne peut pas être payée'],
            ], 422);
        }

        // En mode démo / réel, on initie le paiement via le contrôleur existant
        $paiementController = app(PaiementEnLigneController::class);
        $paiementRequest = new Request([
            'facture_id'    => null,
            'type_paiement' => $request->type_paiement ?? 'cib',
            'montant'       => $reservation->montant,
        ]);

        // Créer une facture fictive pour le paiement en ligne
        $facture = Facture::create([
            'tenant_id'        => config('tenant.current_id'),
            'eleve_id'         => $eleve->id,
            'numero_facture'   => 'MKT-' . strtoupper(Str::random(10)),
            'mois'             => now()->month,
            'annee'            => now()->year,
            'sous_total'       => $reservation->montant,
            'total_ttc'        => $reservation->montant,
            'statut'           => 'émise',
            'date_emission'    => now()->toDateString(),
            'date_echeance'    => now()->addDays(7),
        ]);

        $paiementRequest->merge(['facture_id' => $facture->id]);
        $result = $paiementController->initier($paiementRequest);
        $resultData = $result->getData(true);

        if (!($resultData['success'] ?? false)) {
            return $result;
        }

        $reservation->update([
            'statut'              => 'payee',
            'mode_paiement'       => $paiementRequest->type_paiement,
            'paiement_en_ligne_id' => $resultData['data']['paiement']['id'] ?? null,
        ]);

        if (in_array($reservation->offre->type_cours, ['en_ligne', 'les_deux'])) {
            $lien = $this->visioService->generateLink(
                $reservation->id,
                $reservation->offre->matiere->nom_fr ?? 'Cours'
            );
            $reservation->update(['lien_visio' => $lien]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement initié',
            'data'    => [
                'reservation'  => $reservation->fresh()->load(['offre.matiere', 'offre.enseignant.user']),
                'redirect_url' => $resultData['data']['redirect_url'] ?? null,
                'lien_visio'   => $reservation->lien_visio,
            ],
        ]);
    }

    public function mesReservations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $eleve = Eleve::where('user_id', $user->id)->first();

        $query = Reservation::with([
            'offre.matiere',
            'offre.enseignant.user',
            'offre.wilaya',
            'avis',
        ]);

        if ($eleve) {
            $query->where('eleve_id', $eleve->id);
        }

        $reservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $reservations,
        ]);
    }

    public function annuler(string $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $user = Auth::user();
        $eleve = Eleve::where('user_id', $user->id)->first();

        if (!$eleve || $eleve->id !== $reservation->eleve_id) {
            abort(403, 'Cette réservation ne vous appartient pas');
        }

        if (!in_array($reservation->statut, ['en_attente', 'confirmee'])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_STATUT', 'message' => 'Cette réservation ne peut plus être annulée'],
            ], 422);
        }

        $reservation->update(['statut' => 'annulee']);

        $reservation->offre->increment('places_restantes');

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée',
        ]);
    }

    public function terminer(string $id): JsonResponse
    {
        $reservation = Reservation::with('offre')->findOrFail($id);

        $user = Auth::user();
        $enseignant = Enseignant::where('user_id', $user->id)->first();

        if (!$enseignant || $enseignant->id !== $reservation->offre->enseignant_id) {
            abort(403, 'Seul l\'enseignant de cette offre peut terminer la réservation');
        }

        if ($reservation->statut !== 'payee') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_STATUT', 'message' => 'Seules les réservations payées peuvent être terminées'],
            ], 422);
        }

        $reservation->update(['statut' => 'terminee']);

        return response()->json([
            'success' => true,
            'message' => 'Réservation terminée',
        ]);
    }
}
