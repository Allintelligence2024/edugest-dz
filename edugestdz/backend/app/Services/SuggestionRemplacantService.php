<?php

namespace App\Services;

use App\Models\{Seance, Enseignant};
use Illuminate\Support\Facades\DB;

class SuggestionRemplacantService
{
    private const SCORE_MATIERE     = 30;
    private const SCORE_DISPONIBLE  = 30;
    private const SCORE_EXPERIENCE  = 20;
    private const SCORE_CHARGE      = 20;

    public function suggestions(string $seanceId, int $limit = 5): array
    {
        $seance = Seance::with('cours.matiere', 'cours.groupe', 'cours.enseignant')
            ->findOrFail($seanceId);

        $cours = $seance->cours;
        if (!$cours) {
            return ['seance' => $seance, 'suggestions' => []];
        }

        $matiereId   = $cours->matiere_id;
        $groupeId    = $cours->groupe_id;
        $absentId    = $cours->enseignant_id;
        $dateSeance  = $seance->date_seance->toDateString();
        $heureDebut  = $seance->heure_debut;
        $heureFin    = $seance->heure_fin;

        $candidates = Enseignant::where('tenant_id', config('tenant.current_id'))
            ->where('statut', 'actif')
            ->where('id', '!=', $absentId)
            ->with('matieres')
            ->get();

        $results = [];

        foreach ($candidates as $ens) {
            $matiereMatch = $ens->matieres->contains('id', $matiereId);

            $disponible = !$this->aConflit($ens->id, $dateSeance, $heureDebut, $heureFin, $seance->id);

            $aEnseigneGroupe = DB::table('cours')
                ->where('enseignant_id', $ens->id)
                ->where('groupe_id', $groupeId)
                ->where('id', '!=', $cours->id)
                ->exists();

            $chargeSemaine = $this->compterSeancesSemaine($ens->id, $dateSeance);

            $score = 0;
            if ($matiereMatch)   $score += self::SCORE_MATIERE;
            if ($disponible)     $score += self::SCORE_DISPONIBLE;
            if ($aEnseigneGroupe) $score += self::SCORE_EXPERIENCE;
            $score += max(0, self::SCORE_CHARGE - $chargeSemaine);

            $results[] = [
                'id'                 => $ens->id,
                'nom'                => $ens->nom,
                'prenom'             => $ens->prenom,
                'specialite'         => $ens->specialite,
                'score'              => $score,
                'disponibilite_ok'   => $disponible,
                'matiere_match'      => $matiereMatch,
                'experience_groupe'  => $aEnseigneGroupe,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'seance'      => $seance->load('cours.groupe.matiere', 'cours.enseignant'),
            'suggestions' => array_slice($results, 0, $limit),
        ];
    }

    private function aConflit(string $enseignantId, string $date, string $debut, string $fin, string $excludeSeanceId): bool
    {
        return DB::table('seances')
            ->join('cours', 'cours.id', '=', 'seances.cours_id')
            ->where('cours.enseignant_id', $enseignantId)
            ->where('seances.date_seance', $date)
            ->where('seances.id', '!=', $excludeSeanceId)
            ->where('seances.statut', '!=', 'annulée')
            ->where('seances.heure_debut', '<', $fin)
            ->where('seances.heure_fin', '>', $debut)
            ->exists();
    }

    private function compterSeancesSemaine(string $enseignantId, string $date): int
    {
        $debut = now()->parse($date)->startOfWeek()->toDateString();
        $fin   = now()->parse($date)->endOfWeek()->toDateString();

        return DB::table('seances')
            ->join('cours', 'cours.id', '=', 'seances.cours_id')
            ->where('cours.enseignant_id', $enseignantId)
            ->whereBetween('seances.date_seance', [$debut, $fin])
            ->where('seances.statut', '!=', 'annulée')
            ->count();
    }
}
