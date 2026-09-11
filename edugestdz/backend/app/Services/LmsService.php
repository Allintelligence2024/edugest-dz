<?php

namespace App\Services;

use App\Models\LmsCours;
use App\Models\LmsInscription;
use App\Models\LmsProgression;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsTentativeQuiz;
use Barryvdh\DomPDF\Facade\Pdf;

class LmsService
{
    public function __construct(
        private ParentNotificationService $notif
    ) {}

    public function inscrireEleve(string $coursId, string $eleveId): LmsInscription
    {
        $cours = LmsCours::findOrFail($coursId);

        $inscription = LmsInscription::firstOrCreate(
            ['cours_id' => $coursId, 'eleve_id' => $eleveId],
            [
                'tenant_id'      => config('tenant.current_id'),
                'statut'         => 'actif',
                'progression_pct'=> 0,
            ]
        );

        if ($inscription->wasRecentlyCreated) {
            $cours->increment('nb_inscrits');

            $this->notif->notifier(
                $eleveId,
                'autre',
                "📚 Nouveau cours disponible",
                "Votre enfant est inscrit au cours « {$cours->titre} ». Disponible sur l'application EduGest DZ.",
                ['cours_id' => $coursId, 'type' => 'lms']
            );
        }

        return $inscription;
    }

    public function marquerLeconComplete(
        string $inscriptionId,
        string $leconId,
        int    $tempsSecondes = 0
    ): array {
        $inscription = LmsInscription::with('cours.chapitres.lecons')->findOrFail($inscriptionId);

        $attrs = ['inscription_id' => $inscriptionId, 'lecon_id' => $leconId];
        $progression = LmsProgression::firstOrNew($attrs);
        if (!$progression->exists) {
            $progression->fill([
                'eleve_id'             => $inscription->eleve_id,
                'completee'            => true,
                'temps_passe_secondes' => $tempsSecondes,
                'completee_le'         => now(),
                'nb_vues'              => 1,
            ])->save();
        } else {
            $progression->update([
                'completee'            => true,
                'temps_passe_secondes' => $progression->temps_passe_secondes + $tempsSecondes,
                'completee_le'         => now(),
            ]);
            $progression->increment('nb_vues');
        }

        $result = $this->recalculerProgression($inscription);

        return [
            'progression_pct' => $result['pct'],
            'cours_complete'  => $result['cours_complete'],
            'certificat_url'  => $result['certificat_url'] ?? null,
        ];
    }

    public function recalculerProgression(LmsInscription $inscription): array
    {
        $cours = $inscription->cours->load('chapitres.lecons');

        $totalLecons = $cours->chapitres
            ->flatMap(fn($c) => $c->lecons->where('publiee', true))
            ->count();

        if ($totalLecons === 0) return ['pct' => 0, 'cours_complete' => false];

        $completees = LmsProgression::where('inscription_id', $inscription->id)
            ->where('completee', true)
            ->count();

        $pct = (int) round(($completees / $totalLecons) * 100);

        $update = [
            'progression_pct'       => $pct,
            'nb_lecons_completees'  => $completees,
            'derniere_activite'     => now(),
        ];

        $coursComplete = false;
        $certificatUrl = null;

        if ($pct >= $cours->seuil_completion && !$inscription->complete_le) {
            $update['statut']      = 'termine';
            $update['complete_le'] = now();
            $coursComplete         = true;

            if ($cours->certificat_actif) {
                $certificatUrl = $this->genererCertificat($inscription);
                $update['certificat_url'] = $certificatUrl;
            }

            $this->notif->notifier(
                $inscription->eleve_id,
                'autre',
                "🎓 Cours terminé !",
                "Votre enfant a terminé le cours « {$cours->titre} »" .
                ($certificatUrl ? " — Certificat disponible !" : ''),
                ['cours_id' => $cours->id, 'type' => 'lms_complete']
            );
        }

        $inscription->update($update);

        return ['pct' => $pct, 'cours_complete' => $coursComplete, 'certificat_url' => $certificatUrl];
    }

    public function soumettreTentativeQuiz(
        string $quizId,
        string $eleveId,
        string $inscriptionId,
        array  $reponses,
        int    $dureeSecondes
    ): LmsTentativeQuiz {
        $quiz      = LmsQuiz::with('questions')->findOrFail($quizId);
        $questions = $quiz->questions;

        $nbTentatives = LmsTentativeQuiz::where('quiz_id', $quizId)
            ->where('eleve_id', $eleveId)->count();

        if ($nbTentatives >= $quiz->nb_tentatives_max) {
            throw new \RuntimeException("Nombre maximum de tentatives atteint ({$quiz->nb_tentatives_max}).");
        }

        $scoreObtenu = 0;
        $scoreMax    = $questions->sum('points');
        $detailReponses = [];

        foreach ($questions as $question) {
            $reponse  = $reponses[$question->id] ?? null;
            $correcte = $reponse ? $question->verifierReponse($reponse) : false;

            if ($correcte) $scoreObtenu += $question->points;

            $detailReponses[$question->id] = [
                'reponse'    => $reponse,
                'correcte'   => $correcte,
                'points'     => $correcte ? $question->points : 0,
                'explication'=> $quiz->correction_immediate ? $question->explication : null,
            ];
        }

        $pourcentage = $scoreMax > 0 ? (int) round(($scoreObtenu / $scoreMax) * 100) : 0;
        $reussi      = $pourcentage >= $quiz->seuil_reussite;

        $tentative = LmsTentativeQuiz::create([
            'quiz_id'          => $quizId,
            'eleve_id'         => $eleveId,
            'inscription_id'   => $inscriptionId,
            'score'            => $scoreObtenu,
            'score_max'        => $scoreMax,
            'pourcentage'      => $pourcentage,
            'reussi'           => $reussi,
            'duree_secondes'   => $dureeSecondes,
            'reponses'         => $detailReponses,
            'numero_tentative' => $nbTentatives + 1,
            'debut_le'         => now()->subSeconds($dureeSecondes),
            'fin_le'           => now(),
        ]);

        if ($reussi) {
            $lecon = $quiz->lecon;
            $this->marquerLeconComplete($inscriptionId, $lecon->id, $dureeSecondes);
        }

        return $tentative;
    }

    public function genererCertificat(LmsInscription $inscription): string
    {
        $inscription->load(['cours.enseignant', 'eleve']);
        $cours  = $inscription->cours;
        $eleve  = $inscription->eleve;

        $html = view('pdf.lms-certificat', compact('cours', 'eleve', 'inscription'))->render();

        $pdf      = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        $filename = "certificat-{$eleve->id}-{$cours->id}.pdf";
        $path     = "lms/certificats/{$filename}";

        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());
        return $path;
    }

    public function getDashboard(): array
    {
        return [
            'total_cours'        => LmsCours::where('publie', true)->count(),
            'total_inscrits'     => LmsInscription::count(),
            'cours_completes'    => LmsInscription::where('statut', 'termine')->count(),
            'certificats'        => LmsInscription::whereNotNull('certificat_url')->count(),
            'top_cours'          => LmsCours::withCount('inscriptions')
                ->orderByDesc('inscriptions_count')->limit(5)->get(),
            'activite_recente'   => LmsProgression::with(['eleve:id,nom,prenom', 'lecon:id,titre'])
                ->orderByDesc('updated_at')->limit(10)->get(),
        ];
    }
}
