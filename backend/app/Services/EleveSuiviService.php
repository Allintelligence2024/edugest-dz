<?php

namespace App\Services;

use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EleveSuiviService
{
    public function __construct(private EleveService $eleves) {}

    /**
     * @return array{eleve: Eleve, statistiques: array}
     */
    public function show(Eleve $eleve): array
    {
        $eleve->load([
            'wilaya:id,nom_fr',
            'commune:id,nom_fr',
            'inscriptions' => fn($q) => $q->where('statut', 'validée')
                ->with('groupe:id,nom,matiere_id'),
            'parents:id,nom,prenom,telephone_1,email,lien',
            'notes' => fn($q) => $q->whereNotNull('note')
                ->latest()->limit(50)
                ->with('evaluation:id,type_eval,trimestre,note_sur,coefficient,date_evaluation'),
            'absencesJournalieres' => fn($q) => $q->latest()->limit(20),
            'diagnosticEleve:id,eleve_id,score_risque,niveau_global,matieres_en_danger',
            'factures' => fn($q) => $q->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->with('paiements'),
        ])->loadCount([
            'presences',
            'presences as presences_presentes' => fn($q) => $q->whereIn('statut', ['présent', 'retard']),
            'factures as factures_impayees'    => fn($q) => $q->whereNotIn('statut', ['payée', 'annulée']),
        ]);

        return [
            'eleve'        => $eleve,
            'statistiques' => $this->eleves->getStatsAcademiques($eleve),
        ];
    }

    /**
     * @return array{notes: mixed, moyenne_generale: float, taux_presence: float}
     */
    public function notes(Eleve $eleve, Request $request): array
    {
        $notes = $eleve->notes()
            ->with(['evaluation' => fn($q) => $q->with('groupe.matiere:id,nom_fr,couleur,coefficient')])
            ->when($request->trimestre, fn($q) => $q->whereHas('evaluation', fn($eq) => $eq->where('trimestre', $request->trimestre)))
            ->when($request->groupe_id, fn($q) => $q->whereHas('evaluation', fn($eq) => $eq->where('groupe_id', $request->groupe_id)))
            ->get();

        $parMatiere = $notes->groupBy(fn($n) => $n->evaluation->groupe->matiere->nom_fr)
            ->map(fn($groupNotes, $matiere) => [
                'matiere'     => $matiere,
                'couleur'     => $groupNotes->first()->evaluation->groupe->matiere->couleur ?? '#1E5EBC',
                'coefficient' => $groupNotes->first()->evaluation->groupe->matiere->coefficient,
                'notes'       => $groupNotes->map(fn($n) => [
                    'id'       => $n->id,
                    'note'     => $n->note,
                    'note_sur' => $n->evaluation->note_sur,
                    'appreciation' => $n->appreciation,
                    'type'     => $n->evaluation->type_eval,
                    'date'     => $n->evaluation->date_evaluation,
                    'absent'   => $n->absent,
                ])->values(),
                'moyenne' => $groupNotes->whereNotNull('note')->avg(fn($n) => ($n->note / $n->evaluation->note_sur) * 20),
            ])->values();

        return [
            'notes'            => $parMatiere,
            'moyenne_generale' => $this->eleves->calculerMoyenne($eleve->id, $request->groupe_id, $request->trimestre),
            'taux_presence'    => $this->eleves->calculerTauxPresence($eleve->id),
        ];
    }

    /**
     * @return array{paginator: LengthAwarePaginator, stats: array{total: int, presents: int, absents: int, taux: float}}
     */
    public function presences(Eleve $eleve, Request $request): array
    {
        $paginator = $eleve->presences()
            ->with(['seance' => fn($q) => $q->with([
                'cours.groupe.matiere:id,nom_fr,couleur',
                'cours.enseignant:id,nom,prenom',
            ])])
            ->when($request->mois, fn($q) => $q->whereMonth('created_at', $request->mois))
            ->when($request->annee, fn($q) => $q->whereYear('created_at', $request->annee))
            ->orderByDesc('created_at')
            ->paginate(20);

        return [
            'paginator' => $paginator,
            'stats'     => [
                'total'   => $eleve->presences()->count(),
                'presents'=> $eleve->presences()->whereIn('statut', ['présent','retard'])->count(),
                'absents' => $eleve->presences()->where('statut', 'absent')->count(),
                'taux'    => $this->eleves->calculerTauxPresence($eleve->id),
            ],
        ];
    }

    /**
     * @return array{factures: mixed, financier: array}
     */
    public function paiements(Eleve $eleve): array
    {
        $totalPaye  = $eleve->paiements()
            ->where('statut', 'confirmé')->sum('montant');
        $totalDette = max(0, $eleve->factures()
            ->whereNotIn('statut', ['payée', 'annulée'])->sum('total_ttc')
            - $eleve->paiements()
                ->whereHas('facture', fn($q) => $q->whereNotIn('statut', ['payée', 'annulée']))
                ->where('statut', 'confirmé')
                ->sum('montant'));

        return [
            'factures'  => $eleve->factures()->with('paiements', 'lignes')->orderByDesc('date_emission')->get(),
            'financier' => [
                'total_paye'  => $totalPaye,
                'total_dette' => $totalDette,
                'nb_factures' => $eleve->factures()->count(),
                'nb_impayes'  => $eleve->factures()->whereNotIn('statut', ['payée', 'annulée'])->count(),
            ],
        ];
    }

    /**
     * @return array{bulletins: mixed}
     */
    public function bulletins(Eleve $eleve): array
    {
        return ['bulletins' => $eleve->bulletins()->with('groupe')->orderByDesc('created_at')->get()];
    }

    /**
     * @return array{eleve: Eleve}
     */
    public function statistiques(Eleve $eleve): array
    {
        $eleve->loadCount([
            'inscriptions',
            'presences as total_presences' => fn($q) => $q->whereIn('statut', ['présent', 'retard']),
        ]);

        return ['eleve' => $eleve];
    }
}
