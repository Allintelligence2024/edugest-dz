<?php
namespace App\Services;

use App\Models\{Bulletin, Eleve, Groupe, Note, Presence};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class BulletinService
{
    public function genererBulletins(string $groupeId, string $trimestre, string $anneeScolaire): array
    {
        $groupe = Groupe::findOrFail($groupeId);

        $eleves = Eleve::whereHas('inscriptions', fn($q) =>
            $q->where('groupe_id', $groupeId)->where('statut', 'validée')
        )->get();

        $effectif = $eleves->count();

        $toutesLesNotes = Note::whereHas('evaluation', fn($q) =>
            $q->where('groupe_id', $groupeId)->where('trimestre', $trimestre)
        )
        ->whereIn('eleve_id', $eleves->pluck('id'))
        ->whereNotNull('note')
        ->with('evaluation:id,coefficient,groupe_id,trimestre')
        ->get()
        ->groupBy('eleve_id');

        $toutesLesPresences = Presence::whereHas('seance.cours', fn($q) =>
            $q->where('groupe_id', $groupeId)
        )
        ->whereIn('eleve_id', $eleves->pluck('id'))
        ->selectRaw('eleve_id, statut, COUNT(*) as total')
        ->groupBy('eleve_id', 'statut')
        ->get()
        ->groupBy('eleve_id');

        $moyennes = $eleves->map(function ($eleve) use ($toutesLesNotes) {
            $notesEleve = $toutesLesNotes->get($eleve->id, collect());

            if ($notesEleve->isEmpty()) {
                return ['eleve_id' => $eleve->id, 'moyenne' => 0.0];
            }

            $totalPondere = $notesEleve->sum(fn($n) => $n->note * $n->evaluation->coefficient);
            $totalCoeff   = $notesEleve->sum(fn($n) => $n->evaluation->coefficient);
            $moyenne      = $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : 0.0;

            return ['eleve_id' => $eleve->id, 'moyenne' => $moyenne];
        })->sortByDesc('moyenne');

        $rang = 1;
        $moyennesAvecRang = $moyennes->map(fn($item) => [...$item, 'rang' => $rang++])->keyBy('eleve_id');

        $bulletinsGeneres = [];
        DB::transaction(function () use (
            $eleves, $groupe, $trimestre, $anneeScolaire,
            $effectif, $moyennesAvecRang, $toutesLesPresences, &$bulletinsGeneres
        ) {
            foreach ($eleves as $eleve) {
                $data = $moyennesAvecRang[$eleve->id] ?? ['moyenne' => 0, 'rang' => $effectif];

                $presenceEleve = $toutesLesPresences->get($eleve->id, collect());
                $nbPresent     = $presenceEleve->where('statut', 'présent')->sum('total');
                $nbAbsent      = $presenceEleve->where('statut', 'absent')->sum('total');

                $bulletin = Bulletin::updateOrCreate(
                    [
                        'eleve_id'      => $eleve->id,
                        'groupe_id'     => $groupe->id,
                        'trimestre'     => $trimestre,
                        'annee_scolaire'=> $anneeScolaire,
                    ],
                    [
                        'tenant_id'        => config('tenant.current_id'),
                        'moyenne_generale' => $data['moyenne'],
                        'rang'             => $data['rang'],
                        'effectif_classe'  => $effectif,
                        'appreciation_gen' => $this->getAppreciation($data['moyenne']),
                        'genere_le'        => now(),
                        'genere_par'       => auth('api')->id(),
                    ]
                );

                $pdfPath = $this->genererPDF($bulletin->fresh()->load('eleve', 'groupe.matiere'));
                $bulletin->update(['fichier_url' => $pdfPath]);

                $bulletinsGeneres[] = [
                    'bulletin_id' => $bulletin->id,
                    'eleve'       => $eleve->nom_complet,
                    'moyenne'     => $data['moyenne'],
                    'rang'        => $data['rang'],
                ];
            }
        });

        return $bulletinsGeneres;
    }

    public function calculerMoyenne(string $eleveId, string $groupeId, string $trimestre): float
    {
        $notes = Note::whereHas('evaluation', fn($q) =>
            $q->where('groupe_id', $groupeId)
              ->where('trimestre', $trimestre)
        )
        ->where('eleve_id', $eleveId)
        ->whereNotNull('note')
        ->with('evaluation')
        ->get();

        if ($notes->isEmpty()) return 0.0;

        $totalPondere = $notes->sum(fn($n) => $n->note * $n->evaluation->coefficient);
        $totalCoeff   = $notes->sum(fn($n) => $n->evaluation->coefficient);

        return $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : 0.0;
    }

    public function genererPDF(Bulletin $bulletin): string
    {
        $eleve   = $bulletin->eleve->load(['parents', 'wilaya']);
        $groupe  = $bulletin->groupe->load('matiere');
        $tenant  = app('tenant');

        $notes = Note::with('evaluation')
            ->where('eleve_id', $eleve->id)
            ->whereHas('evaluation', fn($q) =>
                $q->where('groupe_id', $groupe->id)
                  ->where('trimestre', $bulletin->trimestre)
            )
            ->get()
            ->groupBy('evaluation.type_eval');

        $presenceStats = Presence::where('eleve_id', $eleve->id)
            ->whereHas('seance.cours', fn($q) => $q->where('groupe_id', $groupe->id))
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        try {
            $pdf = Pdf::loadView('pdf.bulletin', [
                'bulletin'      => $bulletin,
                'eleve'         => $eleve,
                'groupe'        => $groupe,
                'tenant'        => $tenant,
                'notes'         => $notes,
                'presenceStats' => $presenceStats,
            ])->setPaper('A4', 'portrait');
        } catch (\Exception $e) {
            return '';
        }

        $path = "bulletins/{$bulletin->tenant_id}/{$bulletin->annee_scolaire}/"
              . "{$bulletin->trimestre}/{$eleve->numero_inscription}.pdf";

        \Storage::disk('public')->put($path, $pdf->output());
        return $path;
    }

    private function getAppreciation(float $moyenne): string
    {
        return match(true) {
            $moyenne >= 18 => 'Excellent travail ! Félicitations.',
            $moyenne >= 16 => 'Très bon résultat. Continuez ainsi.',
            $moyenne >= 14 => 'Bon travail. Des efforts réguliers.',
            $moyenne >= 12 => 'Résultats satisfaisants. Peut mieux faire.',
            $moyenne >= 10 => 'Résultats passables. Des efforts s\'imposent.',
            default        => 'Résultats insuffisants. Travail sérieux nécessaire.',
        };
    }
}
