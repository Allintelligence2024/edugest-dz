<?php

namespace App\Console\Commands;

use App\Models\Eleve;
use App\Services\ParentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ResumeHebdomadaireCommand extends Command
{
    protected $signature = 'edugest:resume-hebdo-parents {--force}';
    protected $description = 'Envoyer le résumé hebdomadaire aux parents';

    public function handle(ParentNotificationService $notificationService): int
    {
        $semaine = Carbon::now()->startOfWeek();
        $cleCache = "resume_hebdo_{$semaine->format('Y-m-d')}";

        if (!$this->option('force') && Cache::get($cleCache)) {
            $this->info('Déjà envoyé cette semaine. Utiliser --force pour relancer.');
            return Command::SUCCESS;
        }

        $eleves = Eleve::with('parents')->actifs()->get();
        $envoyes = 0;

        foreach ($eleves as $eleve) {
            $notes = $this->getNotesSemaine($eleve->id, $semaine);
            $absences = $this->getAbsencesSemaine($eleve->id, $semaine);
            $incidents = $this->getIncidentsSemaine($eleve->id, $semaine);

            if ($notes->isEmpty() && $absences->isEmpty() && $incidents->isEmpty()) {
                continue;
            }

            $this->envoyerResumeParent(
                $notificationService,
                $eleve,
                $notes,
                $absences,
                $incidents,
                $semaine
            );

            $envoyes++;
        }

        Cache::put($cleCache, true, now()->addWeek());

        $this->info("✅ Résumé envoyé à {$envoyes} parent(s)");
        return Command::SUCCESS;
    }

    private function getNotesSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        try {
            return DB::table('notes')
                ->join('evaluations', 'notes.evaluation_id', '=', 'evaluations.id')
                ->join('groupes', 'evaluations.groupe_id', '=', 'groupes.id')
                ->leftJoin('matieres', 'groupes.matiere_id', '=', 'matieres.id')
                ->where('notes.eleve_id', $eleveId)
                ->where('evaluations.date_evaluation', '>=', $debut)
                ->where('evaluations.date_evaluation', '<=', $debut->copy()->endOfWeek())
                ->select(
                    'notes.note',
                    'evaluations.note_sur',
                    'evaluations.titre',
                    DB::raw("COALESCE(matieres.nom_fr, 'Cours') as matiere")
                )
                ->get();
        } catch (\Throwable $e) {
            return DB::table('notes')
                ->join('evaluations', 'notes.evaluation_id', '=', 'evaluations.id')
                ->where('notes.eleve_id', $eleveId)
                ->where('evaluations.date_evaluation', '>=', $debut)
                ->where('evaluations.date_evaluation', '<=', $debut->copy()->endOfWeek())
                ->select('notes.note', 'evaluations.note_sur', 'evaluations.titre')
                ->selectRaw("'Cours' as matiere")
                ->get();
        }
    }

    private function getAbsencesSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        return DB::table('presences')
            ->join('seances', 'presences.seance_id', '=', 'seances.id')
            ->where('presences.eleve_id', $eleveId)
            ->where('presences.statut', 'absent')
            ->where('seances.date_seance', '>=', $debut)
            ->where('seances.date_seance', '<=', $debut->copy()->endOfWeek())
            ->select('seances.date_seance', 'presences.motif')
            ->get();
    }

    private function getIncidentsSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        return DB::table('signalements_comportement')
            ->where('eleve_id', $eleveId)
            ->where('created_at', '>=', $debut)
            ->where('created_at', '<=', $debut->copy()->endOfWeek())
            ->select('type', 'gravite', 'description')
            ->get();
    }

    private function envoyerResumeParent(
        ParentNotificationService $service,
        Eleve $eleve,
        \Illuminate\Support\Collection $notes,
        \Illuminate\Support\Collection $absences,
        \Illuminate\Support\Collection $incidents,
        Carbon $semaine
    ): void {
        $titre = "📊 Résumé semaine {$semaine->format('d/m')}";

        $corps = "Notes : {$notes->count()}\n";
        $corps .= "Absences : {$absences->count()}\n";
        $corps .= "Incidents : {$incidents->count()}";

        $meta = [
            'notes' => $notes->toArray(),
            'absences' => $absences->toArray(),
            'incidents' => $incidents->toArray(),
            'semaine' => $semaine->format('d/m/Y'),
        ];

        foreach ($eleve->parents as $parent) {
            $service->notifier(
                $eleve->id,
                'resume_hebdo',
                $titre,
                $corps,
                $meta,
                false,
                false,
                false
            );
        }
    }
}
