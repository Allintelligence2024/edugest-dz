<?php
namespace App\Services;

use App\Models\{Cours, Seance, Salle, Enseignant};
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlanningService
{
    public function genererSeances(Cours $cours): int
    {
        $dateDebut = Carbon::parse($cours->date_debut);
        $dateFin   = $cours->date_fin
            ? Carbon::parse($cours->date_fin)
            : $dateDebut->copy()->addMonths(3);

        $seancesCreees = 0;
        $current       = $dateDebut->copy();

        while ($current->lte($dateFin)) {
            if ($current->dayOfWeek == $cours->jour_semaine) {
                if (!$this->aConflitSeance($cours, $current)) {
                    Seance::firstOrCreate(
                        [
                            'cours_id'    => $cours->id,
                            'date_seance' => $current->toDateString(),
                        ],
                        [
                            'heure_debut' => $cours->heure_debut,
                            'heure_fin'   => $cours->heure_fin,
                            'statut'      => 'planifiée',
                        ]
                    );
                    $seancesCreees++;
                }
            }

            match ($cours->recurrence) {
                'hebdo'      => $current->addWeek(),
                'bimensuel'  => $current->addWeeks(2),
                'mensuel'    => $current->addMonth(),
                default      => $current->addDay(),
            };

            if ($cours->recurrence === 'unique') break;
        }

        return $seancesCreees;
    }

    public function detecterConflits(array $data): array
    {
        $conflits = [];

        $conflitEnseignant = Cours::where('enseignant_id', $data['enseignant_id'])
            ->where('jour_semaine', $data['jour_semaine'])
            ->where('statut', 'actif')
            ->where(function ($q) use ($data) {
                $q->where(function ($inner) use ($data) {
                    $inner->where('heure_debut', '<', $data['heure_fin'])
                          ->where('heure_fin',   '>', $data['heure_debut']);
                });
            })
            ->when(isset($data['exclude_id']), fn($q) =>
                $q->where('id', '!=', $data['exclude_id'])
            )
            ->with('enseignant', 'groupe.matiere')
            ->first();

        if ($conflitEnseignant) {
            $conflits[] = [
                'type'    => 'ENSEIGNANT_OCCUPÉ',
                'message' => "L'enseignant a déjà un cours à ce créneau",
                'details' => [
                    'cours_id'  => $conflitEnseignant->id,
                    'groupe'    => $conflitEnseignant->groupe->nom,
                    'matiere'   => $conflitEnseignant->groupe->matiere?->nom_fr,
                    'heure'     => $conflitEnseignant->heure_debut . ' - ' . $conflitEnseignant->heure_fin,
                ],
            ];
        }

        if (!empty($data['salle_id'])) {
            $conflitSalle = Cours::where('salle_id', $data['salle_id'])
                ->where('jour_semaine', $data['jour_semaine'])
                ->where('statut', 'actif')
                ->where(function ($q) use ($data) {
                    $q->where('heure_debut', '<', $data['heure_fin'])
                      ->where('heure_fin',   '>', $data['heure_debut']);
                })
                ->when(isset($data['exclude_id']), fn($q) =>
                    $q->where('id', '!=', $data['exclude_id'])
                )
                ->with('salle')
                ->first();

            if ($conflitSalle) {
                $conflits[] = [
                    'type'    => 'SALLE_OCCUPÉE',
                    'message' => "La salle est déjà occupée à ce créneau",
                    'details' => [
                        'salle' => $conflitSalle->salle->nom,
                        'heure' => $conflitSalle->heure_debut . ' - ' . $conflitSalle->heure_fin,
                    ],
                ];
            }
        }

        return $conflits;
    }

    public function getPlanningHebdomadaire(
        string $dateDebut,
        string $dateFin,
        array  $filtres = []
    ): array {
        $seances = Seance::with([
                'cours.enseignant',
                'cours.groupe.matiere',
                'cours.salle',
            ])
            ->whereHas('cours', function ($q) use ($filtres) {
                $q->where('statut', 'actif');
                if (!empty($filtres['enseignant_id'])) {
                    $q->where('enseignant_id', $filtres['enseignant_id']);
                }
                if (!empty($filtres['groupe_id'])) {
                    $q->where('groupe_id', $filtres['groupe_id']);
                }
            })
            ->whereBetween('date_seance', [$dateDebut, $dateFin])
            ->orderBy('date_seance')
            ->orderBy('heure_debut')
            ->get();

        return $seances->groupBy(fn($s) =>
            Carbon::parse($s->date_seance)->dayOfWeek
        )->map(fn($daySeances) =>
            $daySeances->map(fn($seance) => [
                'id'          => $seance->id,
                'date'        => $seance->date_seance,
                'heure_debut' => $seance->heure_debut ?? $seance->cours->heure_debut,
                'heure_fin'   => $seance->heure_fin   ?? $seance->cours->heure_fin,
                'statut'      => $seance->statut,
                'matiere'     => $seance->cours->groupe->matiere?->nom_fr,
                'couleur'     => $seance->cours->groupe->matiere?->couleur ?? '#1E5EBC',
                'groupe'      => $seance->cours->groupe->nom,
                'enseignant'  => $seance->cours->enseignant->nom . ' '
                              . $seance->cours->enseignant->prenom,
                'salle'       => $seance->cours->salle?->nom,
                'cours_id'    => $seance->cours->id,
            ])
        )->toArray();
    }

    private function aConflitSeance(Cours $cours, Carbon $date): bool
    {
        $estFerie = \App\Models\CalendrierScolaire::where('type', 'ferie')
            ->whereDate('date_debut', '<=', $date)
            ->whereDate('date_fin',   '>=', $date)
            ->exists();

        return $estFerie;
    }
}
