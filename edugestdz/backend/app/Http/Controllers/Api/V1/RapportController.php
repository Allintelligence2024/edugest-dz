<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Presence, Paiement, Note, Eleve};
use Illuminate\Http\{Request, JsonResponse};

class RapportController extends Controller
{
    public function presence(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin'   => 'required|date|after_or_equal:date_debut',
            'groupe_id'  => 'nullable|uuid|exists:groupes,id',
        ]);

        $presences = Presence::whereBetween('date_seance', [$request->date_debut, $request->date_fin])
            ->when($request->groupe_id, fn($q) => $q->whereHas('seance.cours', fn($q) => $q->where('groupe_id', $request->groupe_id)))
            ->selectRaw("statut, COUNT(*) as total")
            ->groupBy('statut')
            ->get();

        $taux = $presences->sum('total') > 0
            ? round(($presences->whereIn('statut', ['présent', 'retard'])->sum('total') / $presences->sum('total')) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'  => ['debut' => $request->date_debut, 'fin' => $request->date_fin],
                'details'  => $presences,
                'total'    => $presences->sum('total'),
                'taux_presence' => $taux . '%',
            ],
        ]);
    }

    public function financier(Request $request): JsonResponse
    {
        $request->validate([
            'mois'  => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2020',
        ]);

        $paiements = Paiement::whereYear('date_paiement', $request->annee)
            ->whereMonth('date_paiement', $request->mois)
            ->where('statut', 'confirmé')
            ->selectRaw("mode_paiement, SUM(montant) as total")
            ->groupBy('mode_paiement')
            ->get();

        $total = $paiements->sum('total');

        return response()->json([
            'success' => true,
            'data'    => [
                'mois'      => $request->mois,
                'annee'     => $request->annee,
                'total'     => $total,
                'par_mode'  => $paiements,
            ],
        ]);
    }

    public function pedagogique(Request $request): JsonResponse
    {
        $moyennes = Note::selectRaw("inscriptions.groupe_id, AVG(notes.valeur) as moyenne")
            ->join('inscriptions', 'notes.inscription_id', '=', 'inscriptions.id')
            ->when($request->groupe_id, fn($q) => $q->where('inscriptions.groupe_id', $request->groupe_id))
            ->groupBy('inscriptions.groupe_id')
            ->get();

        return response()->json(['success' => true, 'data' => $moyennes]);
    }

    public function attestation(string $eleveId): JsonResponse
    {
        $eleve = Eleve::with(['inscriptions.groupe.matiere', 'wilaya'])->findOrFail($eleveId);

        $attestation = [
            'eleve'       => "{$eleve->nom} {$eleve->prenom}",
            'date_naissance' => $eleve->date_naissance,
            'niveau'      => $eleve->niveau_scolaire,
            'inscriptions'=> $eleve->inscriptions->map(fn($i) => [
                'groupe'  => $i->groupe->nom,
                'matiere' => $i->groupe->matiere->nom_fr,
                'statut'  => $i->statut,
            ]),
        ];

        return response()->json(['success' => true, 'data' => $attestation]);
    }
}
