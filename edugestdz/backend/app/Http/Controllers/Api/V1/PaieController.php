<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Paie, Enseignant};
use App\Services\PaieService;
use Illuminate\Http\{Request, JsonResponse};

class PaieController extends Controller
{
    public function __construct(private readonly PaieService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paies = Paie::with('enseignant')
            ->when($request->enseignant_id, fn($q) => $q->where('enseignant_id', $request->enseignant_id))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->mois, fn($q) => $q->where('mois', $request->mois)->where('annee', $request->annee ?? date('Y')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $paies]);
    }

    public function calculer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enseignant_id' => 'required|uuid|exists:enseignants,id',
            'mois'          => 'required|integer|between:1,12',
            'annee'         => 'required|integer|min:2020',
        ]);

        $enseignant = Enseignant::with('contratsActifs')->findOrFail($validated['enseignant_id']);

        $calcul = $this->service->calculerPaie($enseignant, $validated['mois'], $validated['annee']);
        $calcul['statut'] = 'calculé';

        $paie = Paie::updateOrCreate(
            [
                'enseignant_id' => $enseignant->id,
                'mois'          => $validated['mois'],
                'annee'         => $validated['annee'],
            ],
            array_merge($calcul, ['tenant_id' => config('tenant.current_id')])
        );

        return response()->json([
            'success' => true,
            'message' => 'Paie calculée avec IRG et CNAS algériens',
            'data'    => $paie->load('enseignant'),
        ], 201);
    }

    public function valider(string $id): JsonResponse
    {
        $paie = Paie::findOrFail($id);
        $paie->update(['statut' => 'validé']);
        return response()->json(['success' => true, 'message' => 'Paie validée']);
    }

    public function payer(string $id): JsonResponse
    {
        $paie = Paie::findOrFail($id);
        $paie->update(['statut' => 'payé', 'date_paiement' => now()]);
        return response()->json(['success' => true, 'message' => 'Paie effectuée']);
    }

    public function bulletin(string $id): JsonResponse
    {
        $paie = Paie::with('enseignant')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $paie]);
    }
}
