<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Services\FacturationService;
use Illuminate\Http\{Request, JsonResponse};

class FactureController extends Controller
{
    public function __construct(private FacturationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $factures = Facture::with(['eleve'])
            ->when($request->statut,   fn($q) => $q->where('statut', $request->statut))
            ->when($request->eleve_id, fn($q) => $q->where('eleve_id', $request->eleve_id))
            ->when($request->mois,     fn($q) => $q->where('mois', $request->mois)
                                                    ->where('annee', $request->annee ?? now()->year))
            ->when($request->search,   fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('numero_facture', 'ILIKE', "%{$request->search}%")
                   ->orWhereHas('eleve', fn($eq) =>
                       $eq->where('nom', 'ILIKE', "%{$request->search}%")
                          ->orWhere('prenom', 'ILIKE', "%{$request->search}%")
                   );
            }))
            ->orderByDesc('date_emission')
            ->paginate($request->per_page ?? 15);

        $stats = [
            'total_emises'    => Facture::where('statut', 'émise')->sum('total_ttc'),
            'total_payees'    => Facture::where('statut', 'payée')
                                       ->whereMonth('created_at', now()->month)->sum('total_ttc'),
            'total_en_retard' => Facture::where('statut', 'en_retard')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $factures->items(),
            'meta'    => ['total' => $factures->total(), 'stats' => $stats],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'eleve_id'      => 'required|uuid|exists:eleves,id',
            'date_echeance' => 'required|date',
            'remise_pct'    => 'nullable|numeric|min:0|max:100',
            'lignes'        => 'required|array|min:1',
            'lignes.*.description'   => 'required|string|max:300',
            'lignes.*.prix_unitaire' => 'required|numeric|min:0',
            'lignes.*.quantite'      => 'nullable|numeric|min:0.1',
            'lignes.*.total'         => 'required|numeric|min:0',
            'lignes.*.type_ligne'    => 'required|in:cours,inscription,materiel,remise',
        ]);

        $data            = $request->all();
        $data['lignes']  = collect($request->lignes)->map(fn($l) => [
            ...$l,
            'total' => ($l['quantite'] ?? 1) * $l['prix_unitaire'],
        ])->toArray();

        $facture = $this->service->creerFacture($data);

        return response()->json([
            'success' => true,
            'message' => "Facture {$facture->numero_facture} créée",
            'data'    => $facture->load('lignes', 'eleve'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $facture = Facture::with(['eleve.parents', 'lignes', 'paiements'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $facture]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);

        if (in_array($facture->statut, ['payée', 'annulée'])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FACTURE_IMMUTABLE', 'message' => 'Cette facture ne peut plus être modifiée'],
            ], 422);
        }

        $facture->update($request->only(['date_echeance', 'notes', 'remise_pct']));
        return response()->json(['success' => true, 'data' => $facture->fresh('lignes')]);
    }

    public function destroy(string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);
        $facture->update(['statut' => 'annulée']);
        return response()->json(['success' => true, 'message' => 'Facture annulée']);
    }

    public function pdf(string $id)
    {
        $facture = Facture::findOrFail($id);
        $path    = $this->service->genererFacturePDF($facture);
        return response()->download(storage_path('app/public/' . $path));
    }

    public function envoyer(string $id): JsonResponse
    {
        $facture = Facture::with('eleve.parents')->findOrFail($id);
        $parent = $facture->eleve->parents->firstWhere('pivot.est_principal', true);
        if ($parent?->email) {
            \Mail::to($parent->email)->queue(new \App\Mail\FactureMail($facture));
        }
        return response()->json(['success' => true, 'message' => 'Facture envoyée']);
    }

    /**
     * Générer la facture mensuelle d'un élève (scolarité + transport + cantine)
     * POST /api/v1/factures/generer-mensuelle
     */
    public function genererMensuelle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'          => 'required|uuid|exists:eleves,id',
            'mois'              => 'required|integer|between:1,12',
            'annee'             => 'required|integer|min:2020',
            'tarif_scolarite'   => 'nullable|numeric|min:0',
        ]);

        $facture = $this->service->genererFactureMensuelleEleve(
            $validated['eleve_id'],
            $validated['mois'],
            $validated['annee'],
            $validated['tarif_scolarite'] ?? 0
        );

        if (!$facture) {
            return response()->json([
                'success' => false,
                'message' => 'Facture déjà existante pour ce mois ou aucune ligne à facturer',
                'code'    => 'DEJA_FACTUREE',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => "Facture {$facture->numero_facture} générée",
            'data'    => $facture->load('lignes', 'eleve'),
        ], 201);
    }

    /**
     * Générer les factures mensuelles de tous les élèves actifs
     * POST /api/v1/factures/generer-toutes
     */
    public function genererToutes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'            => 'required|integer|between:1,12',
            'annee'           => 'required|integer|min:2020',
            'tarif_scolarite' => 'nullable|numeric|min:0',
        ]);

        // Dispatcher un job en queue pour éviter le timeout HTTP
        \App\Jobs\GenererFacturesMensuelles::dispatch(
            $validated['mois'],
            $validated['annee'],
            $validated['tarif_scolarite'] ?? 0,
            config('tenant.current_id')
        );

        return response()->json([
            'success' => true,
            'message' => "Génération des factures de {$validated['mois']}/{$validated['annee']} lancée en arrière-plan",
            'data'    => [
                'mois'  => $validated['mois'],
                'annee' => $validated['annee'],
                'statut'=> 'en_cours',
            ],
        ]);
    }
}
