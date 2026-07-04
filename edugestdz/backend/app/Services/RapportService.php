<?php

namespace App\Services;

use App\Models\Eleve;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class RapportService
{
    public function rapportAbsencesMensuel(int $mois, int $annee): \Barryvdh\DomPDF\PDF
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        $eleves = Eleve::where('statut', 'actif')
            ->with(['absencesJournalieres' => fn($q) =>
                $q->whereBetween('date_absence', [$debut, $fin])
                  ->orderBy('date_absence')
            ])
            ->orderBy('nom')
            ->get();

        $data = $eleves->map(function (Eleve $eleve) {
            $absences     = $eleve->absencesJournalieres;
            $justifiees   = $absences->where('statut', 'justifiée')->count();
            $nonJustif    = $absences->where('statut', 'non_justifiée')->count();
            $enAttente    = $absences->where('statut', 'en_attente')->count();

            return [
                'eleve'          => $eleve,
                'total'          => $absences->count(),
                'justifiees'     => $justifiees,
                'non_justifiees' => $nonJustif,
                'en_attente'     => $enAttente,
                'dates'          => $absences->pluck('date_absence')->map(fn($d) =>
                    Carbon::parse($d)->format('d/m')
                )->implode(', '),
                'alerte'         => $absences->count() >= 3,
            ];
        })->filter(fn($d) => $d['total'] > 0);

        $moisLabel = Carbon::create($annee, $mois, 1)->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.rapport-absences', [
            'mois'        => $moisLabel,
            'debut'       => $debut->format('d/m/Y'),
            'fin'         => $fin->format('d/m/Y'),
            'data'        => $data,
            'total_eleves'=> $eleves->count(),
            'nb_absences' => $data->sum('total'),
            'genere_le'   => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }

    public function simulationBEM(string $eleveId): array
    {
        $eleve = Eleve::findOrFail($eleveId);

        $coefficients = [
            'Arabe'                   => 6,
            'Français'                => 4,
            'Mathématiques'           => 5,
            'Sciences Physiques'      => 3,
            'Sciences Naturelles'     => 2,
            'Histoire-Géographie'     => 2,
            'Éducation Islamique'     => 2,
            'Tamazight'               => 2,
            'Anglais'                 => 2,
            'Éducation Technologique' => 1,
            'Arts Plastiques'         => 1,
            'Éducation Musicale'      => 1,
            'Éducation Physique'      => 1,
        ];

        return $this->calculerSimulation($eleve, $coefficients, 'BEM', '4AM');
    }

    public function simulationBAC(string $eleveId, string $filiere): array
    {
        $eleve = Eleve::findOrFail($eleveId);

        $coefficientsParFiliere = [
            'sciences' => [
                'Mathématiques'       => 6,
                'Sciences Physiques'  => 6,
                'Sciences Naturelles' => 5,
                'Arabe'               => 3,
                'Français'            => 3,
                'Anglais'             => 2,
                'Histoire-Géographie' => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
            'maths' => [
                'Mathématiques'       => 9,
                'Sciences Physiques'  => 7,
                'Sciences Naturelles' => 3,
                'Arabe'               => 3,
                'Français'            => 3,
                'Anglais'             => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
            'lettres_langues' => [
                'Arabe'               => 8,
                'Français'            => 6,
                'Anglais'             => 5,
                'Philosophie'         => 4,
                'Histoire-Géographie' => 4,
                'Éducation Islamique' => 2,
                'Mathématiques'       => 2,
                'Sciences Physiques'  => 1,
            ],
            'lettres_philo' => [
                'Arabe'               => 7,
                'Philosophie'         => 7,
                'Histoire-Géographie' => 5,
                'Français'            => 4,
                'Anglais'             => 3,
                'Sciences Islamiques' => 3,
                'Mathématiques'       => 2,
            ],
            'gestion_economie' => [
                'Mathématiques'       => 5,
                'Économie-Gestion'    => 7,
                'Comptabilité'        => 6,
                'Arabe'               => 4,
                'Français'            => 3,
                'Anglais'             => 3,
                'Histoire-Géographie' => 2,
            ],
            'technique_math' => [
                'Mathématiques'           => 8,
                'Sciences Physiques'      => 6,
                'Technologie-Mécanique'   => 6,
                'Arabe'                   => 3,
                'Français'                => 3,
                'Anglais'                 => 2,
                'Éducation Islamique'     => 1,
                'Philosophie'             => 1,
            ],
            'musique' => [
                'Éducation Musicale'  => 10,
                'Arabe'               => 5,
                'Français'            => 4,
                'Anglais'             => 3,
                'Mathématiques'       => 3,
                'Histoire-Géographie' => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
        ];

        $coefficients = $coefficientsParFiliere[$filiere]
            ?? $coefficientsParFiliere['sciences'];

        return $this->calculerSimulation($eleve, $coefficients, 'BAC', $filiere);
    }

    private function calculerSimulation(Eleve $eleve, array $coefficients, string $type, string $contexte): array
    {
        $notes = \App\Models\Note::whereHas('evaluation', fn($q) =>
                $q->whereHas('groupe', fn($q2) =>
                    $q2->whereHas('inscriptions', fn($q3) =>
                        $q3->where('eleve_id', $eleve->id)->where('statut', 'validée')
                    )
                )
            )
            ->where('eleve_id', $eleve->id)
            ->whereNotNull('note')
            ->with('evaluation.groupe.matiere:id,nom_fr')
            ->get()
            ->groupBy('evaluation.groupe.matiere.nom_fr')
            ->map(fn($notes) => round($notes->avg('note'), 2));

        $totalPondere = 0;
        $totalCoeff   = 0;
        $detail       = [];

        foreach ($coefficients as $matiere => $coeff) {
            $note = $notes[$matiere] ?? null;
            $detail[] = [
                'matiere'    => $matiere,
                'coefficient'=> $coeff,
                'note'       => $note,
                'points'     => $note !== null ? round($note * $coeff, 2) : null,
                'manquante'  => $note === null,
            ];

            if ($note !== null) {
                $totalPondere += $note * $coeff;
                $totalCoeff   += $coeff;
            }
        }

        $moyenne = $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : null;

        $mention = match (true) {
            $moyenne === null         => null,
            $moyenne >= 18            => 'Très Bien avec Félicitations',
            $moyenne >= 16            => 'Très Bien',
            $moyenne >= 14            => 'Bien',
            $moyenne >= 12            => 'Assez Bien',
            $moyenne >= 10            => 'Passable',
            default                   => 'Insuffisant',
        };

        $matieresSansNote = collect($detail)->where('manquante', true)->count();

        return [
            'eleve'               => [
                'id'     => $eleve->id,
                'nom'    => $eleve->nom_complet,
                'niveau' => $eleve->niveau_scolaire,
            ],
            'type'                => $type,
            'contexte'            => $contexte,
            'moyenne_simulee'     => $moyenne,
            'mention_simulee'     => $mention,
            'total_coefficients'  => array_sum($coefficients),
            'coefficients_couverts' => $totalCoeff,
            'matieres_sans_note'  => $matieresSansNote,
            'fiabilite'           => $matieresSansNote === 0 ? 'haute'
                : ($matieresSansNote <= 2 ? 'moyenne' : 'faible'),
            'detail'              => $detail,
            'avertissement'       => $matieresSansNote > 0
                ? "{$matieresSansNote} matière(s) sans notes — la simulation est approximative."
                : null,
        ];
    }
}
