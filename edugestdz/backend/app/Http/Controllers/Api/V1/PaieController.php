<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Paie, Enseignant};
use Illuminate\Http\{Request, JsonResponse};

class PaieController extends Controller
{
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
        $contrat = $enseignant->contratsActifs->first();

        $nbSeances = $enseignant->seances()
            ->whereMonth('date_seance', $validated['mois'])
            ->whereYear('date_seance', $validated['annee'])
            ->where('statut', 'terminée')
            ->count();

        $montant = $contrat
            ? ($contrat->salaire_base ?? $contrat->tarif_horaire * $nbSeances * 2)
            : $enseignant->tarif_horaire * $nbSeances * 2;

        $paie = Paie::create([
            'enseignant_id' => $enseignant->id,
            'mois'          => $validated['mois'],
            'annee'         => $validated['annee'],
            'nb_seances'    => $nbSeances,
            'montant_brut'  => $montant,
            'montant_net'   => $montant * 0.91,
            'statut'        => 'calculée',
            'date_paie'     => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Paie calculée', 'data' => $paie->load('enseignant')], 201);
    }

    public function valider(string $id): JsonResponse
    {
        $paie = Paie::findOrFail($id);
        $paie->update(['statut' => 'validée']);
        return response()->json(['success' => true, 'message' => 'Paie validée']);
    }

    public function payer(string $id): JsonResponse
    {
        $paie = Paie::findOrFail($id);
        $paie->update(['statut' => 'payée', 'date_paiement' => now()]);
        return response()->json(['success' => true, 'message' => 'Paie effectuée']);
    }

    public function bulletin(string $id): JsonResponse
    {
        $paie = Paie::with('enseignant')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $paie]);
    }
}
