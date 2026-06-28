<?php
namespace App\Services;

use App\Models\{Cours, Seance, Salle, Enseignant};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    private array $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    public function getPlanningHebdomadaire(string $debut, string $fin, array $filtres = []): array
    {
        $query = Cours::with(['groupe.matiere', 'enseignant', 'salle'])
            ->where('statut', 'actif');

        if (!empty($filtres['enseignant_id'])) {
            $query->where('enseignant_id', $filtres['enseignant_id']);
        }
        if (!empty($filtres['salle_id'])) {
            $query->where('salle_id', $filtres['salle_id']);
        }
        if (!empty($filtres['groupe_id'])) {
            $query->where('groupe_id', $filtres['groupe_id']);
        }

        $cours = $query->get();

        $debutDate = Carbon::parse($debut);
        $finDate   = Carbon::parse($fin);
        $planning  = [];

        for ($date = $debutDate->copy(); $date->lte($finDate); $date->addDay()) {
        $jourSemaine = $this->getJourIndex($date->dayOfWeek);
            $seancesDuJour = $cours->filter(fn($c) => $c->jour_semaine === $jourSemaine);

            if ($seancesDuJour->isNotEmpty()) {
                $planning[] = [
                    'date'     => $date->toDateString(),
                    'jour'     => $this->jours[$jourSemaine] ?? 'inconnu',
                    'seances'  => $seancesDuJour->values()->toArray(),
                ];
            }
        }

        return $planning;
    }

    public function verifierConflits(string $enseignantId, string $jourSemaine, string $heureDebut, string $heureFin, ?string $excludeCoursId = null): array
    {
        $query = Cours::where('enseignant_id', $enseignantId)
            ->where('jour_semaine', $jourSemaine)
            ->where('statut', 'actif');

        if ($excludeCoursId) {
            $query->where('id', '!=', $excludeCoursId);
        }

        $coursExistants = $query->get();
        $conflits = [];

        $nouveauDebut = Carbon::parse($heureDebut);
        $nouveauFin   = Carbon::parse($heureFin);

        foreach ($coursExistants as $c) {
            $existantDebut = Carbon::parse($c->heure_debut);
            $existantFin   = Carbon::parse($c->heure_fin);

            if ($nouveauDebut->lt($existantFin) && $nouveauFin->gt($existantDebut)) {
                $conflits[] = [
                    'cours_id'    => $c->id,
                    'groupe'      => $c->groupe->nom ?? 'N/A',
                    'heure_debut' => $c->heure_debut,
                    'heure_fin'   => $c->heure_fin,
                ];
            }
        }

        return $conflits;
    }

    public function verifierDisponibiliteSalle(string $salleId, string $jourSemaine, string $heureDebut, string $heureFin, ?string $excludeCoursId = null): bool
    {
        $query = Cours::where('salle_id', $salleId)
            ->where('jour_semaine', $jourSemaine)
            ->where('statut', 'actif');

        if ($excludeCoursId) {
            $query->where('id', '!=', $excludeCoursId);
        }

        $nouveauDebut = Carbon::parse($heureDebut);
        $nouveauFin   = Carbon::parse($heureFin);

        return !$query->get()->contains(function ($c) use ($nouveauDebut, $nouveauFin) {
            $debut = Carbon::parse($c->heure_debut);
            $fin   = Carbon::parse($c->heure_fin);
            return $nouveauDebut->lt($fin) && $nouveauFin->gt($debut);
        });
    }

    public function genererSeances(Cours $cours): int
    {
        $generated = 0;
        $debut     = now()->addWeek()->startOfWeek();
        $fin       = now()->addWeek()->endOfWeek();
        $jourIndex = $this->getJourIndex($debut->dayOfWeek);

        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            if ($date->dayOfWeek == $this->getDayOfWeekFromJourIndex($jourIndex)) {
                $existe = Seance::where('cours_id', $cours->id)
                    ->where('date_seance', $date->toDateString())
                    ->exists();

                if (!$existe) {
                    Seance::create([
                        'tenant_id'   => $cours->tenant_id,
                        'cours_id'    => $cours->id,
                        'groupe_id'   => $cours->groupe_id,
                        'date_seance' => $date->toDateString(),
                        'statut'      => 'planifiée',
                    ]);
                    $generated++;
                }
            }
        }

        return $generated;
    }

    private function getJourIndex(int $dayOfWeek): int
    {
        return match ($dayOfWeek) {
            Carbon::SUNDAY    => 0,
            Carbon::MONDAY    => 1,
            Carbon::TUESDAY   => 2,
            Carbon::WEDNESDAY => 3,
            Carbon::THURSDAY  => 4,
            Carbon::FRIDAY    => 5,
            Carbon::SATURDAY  => 6,
            default           => 0,
        };
    }

    private function getDayOfWeekFromJourIndex(int $index): int
    {
        return match ($index) {
            0 => Carbon::SUNDAY,
            1 => Carbon::MONDAY,
            2 => Carbon::TUESDAY,
            3 => Carbon::WEDNESDAY,
            4 => Carbon::THURSDAY,
            5 => Carbon::FRIDAY,
            6 => Carbon::SATURDAY,
            default => Carbon::SUNDAY,
        };
    }
}
