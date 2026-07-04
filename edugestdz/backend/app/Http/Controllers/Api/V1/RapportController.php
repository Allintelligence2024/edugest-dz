<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Presence, Paiement, Note, Eleve};
use App\Services\RapportService;
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

    public function absencesPDF(Request $request, RapportService $rapport)
    {
        $request->validate([
            'mois'  => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2020',
        ]);

        try {
            $pdf = $rapport->rapportAbsencesMensuel((int)$request->mois, (int)$request->annee);

            return $pdf->download("absences_{$request->mois}_{$request->annee}.pdf");
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport : ' . $e->getMessage(),
            ], 500);
        }
    }

    public function simulationBEM(Request $request, RapportService $rapport): JsonResponse
    {
        $request->validate([
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ]);

        $resultat = $rapport->simulationBEM($request->eleve_id);

        return response()->json([
            'success' => true,
            'data'    => $resultat,
        ]);
    }

    public function simulationBAC(Request $request, RapportService $rapport): JsonResponse
    {
        $request->validate([
            'eleve_id' => 'required|uuid|exists:eleves,id',
            'filiere'  => 'required|string|in:sciences,maths,lettres_langues,lettres_philo,gestion_economie,technique_math,musique',
        ]);

        $resultat = $rapport->simulationBAC($request->eleve_id, $request->filiere);

        return response()->json([
            'success' => true,
            'data'    => $resultat,
        ]);
    }

    public function absencesStats(Request $request): JsonResponse
    {
        $request->validate([
            'eleve_id' => 'nullable|uuid|exists:eleves,id',
            'mois'     => 'nullable|integer|between:1,12',
            'annee'    => 'nullable|integer|min:2020',
        ]);

        $query = \App\Models\AbsenceJournaliere::query();

        if ($request->eleve_id) {
            $query->where('eleve_id', $request->eleve_id);
        }

        if ($request->mois && $request->annee) {
            $query->whereYear('date_absence', $request->annee)
                  ->whereMonth('date_absence', $request->mois);
        } elseif ($request->annee) {
            $query->whereYear('date_absence', $request->annee);
        }

        $total       = $query->count();
        $justifiees  = (clone $query)->where('statut', 'justifiée')->count();
        $nonJustif   = (clone $query)->where('statut', 'non_justifiée')->count();
        $enAttente   = (clone $query)->where('statut', 'en_attente')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total'         => $total,
                'justifiees'    => $justifiees,
                'non_justifiees'=> $nonJustif,
                'en_attente'    => $enAttente,
                'taux_absence'  => $total > 0
                    ? round(($nonJustif / $total) * 100, 1) . '%'
                    : '0%',
            ],
        ]);
    }
}
