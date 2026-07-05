<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\DiagnosticEleve;
use App\Models\HistoriqueDiagnostic;
use App\Models\Note;
use App\Models\AbsenceJournaliere;
use App\Models\Billet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DiagnosticService
{
    private const SEUIL_CRITIQUE        = 5.0;
    private const SEUIL_DANGER          = 8.0;
    private const SEUIL_VIGILANCE       = 10.0;
    private const SEUIL_EXCELLENCE      = 15.0;
    private const SEUIL_ABSENCES_ALERTE = 3;
    private const SEUIL_RETARDS_ALERTE  = 5;
    private const SEUIL_CHUTE_ALERTE    = 3.0;
    private const SERIE_CRITIQUE        = 3;

    public function analyserEleve(string $eleveId): DiagnosticEleve
    {
        $eleve = Eleve::findOrFail($eleveId);

        $notes          = $this->getNotesTrimestre($eleveId);
        $comportement   = $this->getComportementMois($eleveId);
        $notesPrecedent = $this->getNotesTrimestre($eleveId, -1);

        $moyenneActuelle  = $notes->avg('note') ?? null;
        $moyennePrecedent = $notesPrecedent->avg('note') ?? null;
        $tendance         = ($moyenneActuelle && $moyennePrecedent)
            ? round($moyenneActuelle - $moyennePrecedent, 2)
            : null;

        $nbSous5      = $notes->where('note', '<', 5)->count();
        $nbSous10     = $notes->where('note', '<', 10)->count();
        $serieCritique = $this->calculerSerieCritique($notes);

        $parMatiere = $notes->groupBy(fn(Note $n) => $n->evaluation?->groupe?->matiere?->nom_fr ?? 'Autre')
            ->map(fn($group) => round($group->avg('note'), 2));

        $matieresDanger = $parMatiere->filter(fn($m) => $m < self::SEUIL_DANGER)
            ->map(fn($moy, $mat) => [
                'matiere' => $mat,
                'moyenne' => $moy,
                'niveau'  => $moy < self::SEUIL_CRITIQUE ? 'critique' : 'danger',
            ])->values()->toArray();

        $matieresExcellentes = $parMatiere->filter(fn($m) => $m >= self::SEUIL_EXCELLENCE)
            ->map(fn($moy, $mat) => ['matiere' => $mat, 'moyenne' => $moy])
            ->values()->toArray();

        $scoreRisque = $this->calculerScoreRisque(
            $moyenneActuelle,
            $tendance,
            $serieCritique,
            $comportement,
            $nbSous5
        );

        $niveauGlobal = $this->determinerNiveau($scoreRisque, $moyenneActuelle);

        $rattrapageRequis   = $niveauGlobal === 'danger' || $niveauGlobal === 'critique';
        $convocationRequise = $niveauGlobal === 'critique'
            || ($niveauGlobal === 'danger' && $serieCritique >= self::SERIE_CRITIQUE)
            || $comportement['absences'] > self::SEUIL_ABSENCES_ALERTE * 2;
        $mentionExcellence = $niveauGlobal === 'excellent'
            && ($moyenneActuelle >= 17) && ($comportement['absences'] === 0);

        $diagnostic = DiagnosticEleve::updateOrCreate(
            ['eleve_id' => $eleveId],
            [
                'tenant_id'                        => $eleve->tenant_id,
                'niveau_global'                    => $niveauGlobal,
                'score_risque'                     => $scoreRisque,
                'moyenne_generale'                 => $moyenneActuelle,
                'moyenne_trimestre_precedent'      => $moyennePrecedent,
                'tendance'                         => $tendance,
                'nb_notes_sous_5'                  => $nbSous5,
                'nb_notes_sous_10'                 => $nbSous10,
                'nb_notes_consecutives_sous_5'     => $serieCritique,
                'matieres_en_danger'               => $matieresDanger,
                'matieres_excellentes'             => $matieresExcellentes,
                'nb_absences_mois'                 => $comportement['absences'],
                'nb_retards_mois'                  => $comportement['retards'],
                'nb_billets_mois'                  => $comportement['billets'],
                'rattrapage_requis'                => $rattrapageRequis,
                'convocation_requise'              => $convocationRequise,
                'mention_excellence'               => $mentionExcellence,
                'derniere_analyse'                 => now(),
                'prochaine_analyse'                => now()->addDay(),
            ]
        );

        HistoriqueDiagnostic::create([
            'tenant_id'       => $eleve->tenant_id,
            'eleve_id'        => $eleveId,
            'niveau_global'   => $niveauGlobal,
            'score_risque'    => $scoreRisque,
            'moyenne_generale'=> $moyenneActuelle,
            'tendance'        => $tendance,
            'details'         => [
                'matieres_danger' => $matieresDanger,
                'serie_critique'  => $serieCritique,
                'comportement'    => $comportement,
            ],
            'analyse_le'      => now(),
        ]);

        Log::info("Diagnostic élève {$eleveId}: {$niveauGlobal} (score: {$scoreRisque})");

        return $diagnostic;
    }

    public function analyserTousLesEleves(?string $tenantId = null): array
    {
        $query = Eleve::where('statut', 'actif');
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $eleves  = $query->pluck('id');
        $resultats = ['total' => 0, 'critiques' => 0, 'dangers' => 0, 'excellents' => 0];

        foreach ($eleves as $eleveId) {
            try {
                $diag = $this->analyserEleve($eleveId);
                $resultats['total']++;
                if ($diag->niveau_global === 'critique')  $resultats['critiques']++;
                if ($diag->niveau_global === 'danger')    $resultats['dangers']++;
                if ($diag->niveau_global === 'excellent') $resultats['excellents']++;
            } catch (\Throwable $e) {
                Log::warning("Diagnostic échoué pour élève {$eleveId}: " . $e->getMessage());
            }
        }

        return $resultats;
    }

    private function calculerScoreRisque(
        ?float $moyenne,
        ?float $tendance,
        int $serieCritique,
        array $comportement,
        int $nbSous5
    ): float {
        $score = 0;

        if ($moyenne !== null) {
            if ($moyenne < self::SEUIL_CRITIQUE)   $score += 50;
            elseif ($moyenne < self::SEUIL_DANGER) $score += 35;
            elseif ($moyenne < self::SEUIL_VIGILANCE) $score += 20;
            elseif ($moyenne >= self::SEUIL_EXCELLENCE) $score += 0;
            else $score += 10;
        }

        if ($tendance !== null) {
            if ($tendance <= -self::SEUIL_CHUTE_ALERTE) $score += 20;
            elseif ($tendance < 0)                      $score += 10;
            elseif ($tendance > 2)                      $score -= 5;
        }

        $score += min($serieCritique * 5, 15);

        if ($comportement['absences'] > self::SEUIL_ABSENCES_ALERTE)
            $score += min($comportement['absences'] * 2, 10);
        if ($comportement['retards'] > self::SEUIL_RETARDS_ALERTE)
            $score += 5;

        return max(0, min(100, round($score, 2)));
    }

    private function determinerNiveau(float $score, ?float $moyenne): string
    {
        if ($moyenne !== null && $moyenne < self::SEUIL_CRITIQUE) return 'critique';
        if ($moyenne !== null && $moyenne >= self::SEUIL_EXCELLENCE && $score <= 10) return 'excellent';
        if ($score >= 76) return 'critique';
        if ($score >= 56) return 'danger';
        if ($score >= 31) return 'vigilance';
        return 'normal';
    }

    private function calculerSerieCritique($notes): int
    {
        $max = 0;
        $current = 0;
        foreach ($notes->sortBy('created_at') as $note) {
            if ($note->note < 5) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }
        return $max;
    }

    private function getNotesTrimestre(string $eleveId, int $offset = 0)
    {
        $debut = now()->startOfQuarter()->addMonths($offset * 3);
        $fin   = $debut->copy()->endOfQuarter();

        return Note::where('eleve_id', $eleveId)
            ->whereNotNull('note')
            ->whereBetween('created_at', [$debut, $fin])
            ->with('evaluation.groupe.matiere:id,nom_fr')
            ->get();
    }

    private function getComportementMois(string $eleveId): array
    {
        $debut = now()->startOfMonth();
        $fin   = now()->endOfMonth();

        $absences = AbsenceJournaliere::where('eleve_id', $eleveId)
            ->whereBetween('date_absence', [$debut->format('Y-m-d'), $fin->format('Y-m-d')])
            ->count();

        $billets = Billet::where('eleve_id', $eleveId)
            ->whereBetween('created_at', [$debut, $fin])
            ->get();

        return [
            'absences' => $absences,
            'retards'  => $billets->where('type', 'retard')->count(),
            'billets'  => $billets->count(),
        ];
    }

    public function getDashboard(?string $tenantId = null): array
    {
        $query = DiagnosticEleve::query();
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $tous = $query->with('eleve:id,nom,prenom,niveau_scolaire')->get();

        return [
            'total_analyses'    => $tous->count(),
            'par_niveau'        => [
                'excellent' => $tous->where('niveau_global', 'excellent')->count(),
                'normal'    => $tous->where('niveau_global', 'normal')->count(),
                'vigilance' => $tous->where('niveau_global', 'vigilance')->count(),
                'danger'    => $tous->where('niveau_global', 'danger')->count(),
                'critique'  => $tous->where('niveau_global', 'critique')->count(),
            ],
            'actions_requises'  => [
                'rattrapages'  => $tous->where('rattrapage_requis', true)->count(),
                'convocations' => $tous->where('convocation_requise', true)->count(),
                'excellents'   => $tous->where('mention_excellence', true)->count(),
            ],
            'top_risque'        => $tous->sortByDesc('score_risque')->take(5)
                ->map(fn($d) => [
                    'eleve'   => $d->eleve?->nom_complet ?? '—',
                    'niveau'  => $d->niveau_global,
                    'score'   => $d->score_risque,
                    'moyenne' => $d->moyenne_generale,
                ])->values(),
            'top_excellence'    => $tous->where('niveau_global', 'excellent')
                ->sortByDesc('moyenne_generale')->take(5)
                ->map(fn($d) => [
                    'eleve'   => $d->eleve?->nom_complet ?? '—',
                    'moyenne' => $d->moyenne_generale,
                ])->values(),
        ];
    }
}
