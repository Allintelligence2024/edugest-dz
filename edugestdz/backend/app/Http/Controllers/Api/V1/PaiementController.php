<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\FacturationService;
use Illuminate\Http\{Request, JsonResponse};

class PaiementController extends Controller
{
    public function __construct(private FacturationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paiements = Paiement::with(['facture', 'eleve'])
            ->when($request->eleve_id,    fn($q) => $q->where('eleve_id', $request->eleve_id))
            ->when($request->mode,        fn($q) => $q->where('mode_paiement', $request->mode))
            ->when($request->date_debut,  fn($q) => $q->where('date_paiement', '>=', $request->date_debut))
            ->when($request->date_fin,    fn($q) => $q->where('date_paiement', '<=', $request->date_fin))
            ->orderByDesc('date_paiement')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $paiements->items(),
            'meta'    => ['total' => $paiements->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'facture_id'    => 'required|uuid|exists:factures,id',
            'montant'       => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:espèces,cib,dahabia,baridimob,virement,chèque',
            'date_paiement' => 'required|date|before_or_equal:today',
            'notes'         => 'nullable|string',
        ]);

        $paiement = $this->service->enregistrerPaiement($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré avec succès !',
            'data'    => $paiement->load('facture'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $paiement = Paiement::with(['facture.eleve', 'facture.lignes'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $paiement]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $paiement = Paiement::findOrFail($id);
        $paiement->update($request->only(['notes', 'statut']));
        return response()->json(['success' => true, 'data' => $paiement]);
    }

    public function destroy(string $id): JsonResponse
    {
        $paiement = Paiement::findOrFail($id);
        $paiement->update(['statut' => 'annulé']);
        return response()->json(['success' => true, 'message' => 'Paiement annulé']);
    }

    public function recu(string $id)
    {
        $paiement = Paiement::findOrFail($id);
        if (!$paiement->recu_url) {
            $path = $this->service->genererRecuPDF($paiement);
        } else {
            $path = $paiement->recu_url;
        }
        return response()->download(storage_path('app/public/' . $path));
    }

    public function caisseJour(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();
        $paiements = Paiement::with(['eleve', 'facture'])
            ->where('date_paiement', $date)
            ->where('statut', 'confirmé')
            ->get();

        $totalParMode = $paiements->groupBy('mode_paiement')
            ->map(fn($g) => $g->sum('montant'));

        return response()->json([
            'success'   => true,
            'date'      => $date,
            'paiements' => $paiements,
            'stats'     => [
                'total'        => $paiements->sum('montant'),
                'nb_paiements' => $paiements->count(),
                'par_mode'     => $totalParMode,
            ],
        ]);
    }
}
