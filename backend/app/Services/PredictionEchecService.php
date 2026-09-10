<?php

namespace App\Services;

use App\Models\{Eleve, DiagnosticEleve};
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;

class PredictionEchecService
{
    private const COEFFICIENTS = [
        'intercept'                    => -2.1,
        'tendance_4sem'                => -0.48,
        'pct_notes_sous_moy'           =>  2.85,
        'nb_absences_justifiees_ratio' =>  0.62,
        'chute_max_1sem'               => -0.73,
        'serie_chutes_consecutives'    =>  0.91,
        'nb_matieres_danger'           =>  0.67,
        'variance_notes'               =>  0.19,
        'nb_billets_4sem'              =>  0.44,
        'correlation_absence_note'     =>  1.12,
        'score_ews_normalise'          =>  0.038,
    ];

    private const SEUIL_FAIBLE = 25.0;
    private const SEUIL_MODERE = 50.0;
    private const SEUIL_ELEVE  = 70.0;

    public function predire(
        string $eleveId,
        string $horizon  = '4_semaines',
        string $tenantId = null
    ): array {
        $tenantId = $tenantId ?? config('tenant.current_id');

        try {
            $features = $this->extraireFeatures($eleveId, $tenantId);

            if ($features['nb_notes_4sem'] === 0) {
                return $this->fallbackEWS($eleveId, $horizon);
            }

            $probabilite = $this->calculerProbabilite($features);

            $probabiliteAjustee = $this->ajusterHorizon($probabilite, $horizon);

            $confiance = $this->calculerConfiance($features, $probabiliteAjustee);

            $niveauRisque = $this->classifierRisque($probabiliteAjustee);

            $facteursRisque = $this->expliquerPrediction($features, $probabiliteAjustee);

            $recommandations = $this->genererRecommandations($niveauRisque, $features, $facteursRisque);

            $predictionId = $this->persisterPrediction(
                $eleveId, $tenantId, $probabiliteAjustee, $confiance,
                $horizon, $niveauRisque, $features, $facteursRisque, $recommandations
            );

            return [
                'prediction_id'   => $predictionId,
                'eleve_id'        => $eleveId,
                'probabilite'     => round($probabiliteAjustee, 1),
                'confiance'       => round($confiance, 1),
                'horizon'         => $horizon,
                'niveau_risque'   => $niveauRisque,
                'facteurs_risque' => $facteursRisque,
                'recommandations' => $recommandations,
                'moteur'          => 'logistique_v1',
                'resume'          => $this->genererResume($niveauRisque, $probabiliteAjustee, $facteursRisque),
            ];
        } catch (\Throwable $e) {
            Log::warning("PredictionEchec: fallback DiagnosticService pour {$eleveId}: " . $e->getMessage());
            return $this->fallbackEWS($eleveId, $horizon);
        }
    }

    public function predireTenant(string $tenantId): array
    {
        $eleveIds = $this->safeQuery(fn() => DB::table('eleves')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'actif')
            ->pluck('id'), collect());

        $resultats = [];
        foreach ($eleveIds as $eleveId) {
            $resultats[] = $this->predire($eleveId, '4_semaines', $tenantId);
        }

        usort($resultats, fn($a, $b) => $b['probabilite'] <=> $a['probabilite']);

        return $resultats;
    }

    private function extraireFeatures(string $eleveId, string $tenantId): array
    {
        $maintenant = now();
        $il_y_a_4sem = $maintenant->copy()->subWeeks(4)->toDateString();
        $il_y_a_8sem = $maintenant->copy()->subWeeks(8)->toDateString();

        $notes4sem = $this->safeQuery(fn() => DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->where('n.eleve_id', $eleveId)
            ->where('e.date_evaluation', '>=', $il_y_a_4sem)
            ->select('n.note', 'e.date_evaluation as date')
            ->orderBy('e.date_evaluation')
            ->get(), collect());

        $notes8sem = $this->safeQuery(fn() => DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->where('n.eleve_id', $eleveId)
            ->where('e.date_evaluation', '>=', $il_y_a_8sem)
            ->select('n.note', 'e.date_evaluation as date')
            ->orderBy('e.date_evaluation')
            ->get(), collect());

        $absences = $this->safeQuery(fn() => DB::table('absences_journalieres')
            ->where('eleve_id', $eleveId)
            ->where('date_absence', '>=', $il_y_a_4sem)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN motif IS NOT NULL AND motif != '' THEN 1 ELSE 0 END) as justifiees")
            ->first(), (object) ['total' => 0, 'justifiees' => 0]);

        $nbBillets = $this->safeQuery(fn() => DB::table('billets')
            ->where('eleve_id', $eleveId)
            ->where('created_at', '>=', $il_y_a_4sem)
            ->count(), 0);

        $diagnostic = $this->safeQuery(fn() => DB::table('diagnostics_eleves')
            ->where('eleve_id', $eleveId)
            ->value('score_risque'), null);

        $moyenneActuelle = $notes4sem->avg('note') ?? 10.0;
        $moyennePrecedente = $notes8sem->take($notes8sem->count() - $notes4sem->count())->avg('note') ?? $moyenneActuelle;

        $tendance4sem = $this->calculerTendanceLineaire($notes4sem);
        $chuteMax1sem = $this->calculerChuteMaximale($notes4sem);
        $variance     = $this->calculerVariance($notes4sem);
        $serieChu     = $this->calculerSerieChutes($notes4sem);

        $totalAbsences = (int) ($absences->total ?? 0);
        $absencesJust  = (int) ($absences->justifiees ?? 0);
        $ratioNonJust  = $totalAbsences > 0
            ? ($totalAbsences - $absencesJust) / $totalAbsences
            : 0.0;

        $pctSousMoy = $notes4sem->count() > 0
            ? $notes4sem->filter(fn($n) => $n->note < 10)->count() / $notes4sem->count()
            : 0.0;

        $nbMatieresDanger = $this->safeQuery(fn() => (int) DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->join('cours as c', 'e.groupe_id', '=', 'c.groupe_id')
            ->where('n.eleve_id', $eleveId)
            ->where('e.date_evaluation', '>=', $il_y_a_4sem)
            ->select('c.matiere_id')
            ->groupBy('c.matiere_id')
            ->havingRaw('AVG(n.note) < 8')
            ->count(), 0);

        $correlationAbsNote = ($totalAbsences >= 2 && $tendance4sem < -0.5) ? 0.7 : 0.0;

        return [
            'moyenne_actuelle'             => round($moyenneActuelle, 2),
            'moyenne_precedente'           => round($moyennePrecedente, 2),
            'nb_notes_4sem'                => $notes4sem->count(),
            'nb_absences_4sem'             => $totalAbsences,
            'nb_absences_justifiees'       => $absencesJust,
            'nb_billets_4sem'              => $nbBillets,
            'score_ews_actuel'             => (int) ($diagnostic ?? 0),
            'tendance_4sem'                => max(-5.0, min(5.0, $tendance4sem)),
            'pct_notes_sous_moy'           => round($pctSousMoy, 3),
            'nb_absences_justifiees_ratio' => round($ratioNonJust, 3),
            'chute_max_1sem'               => max(-10.0, min(0.0, $chuteMax1sem)),
            'serie_chutes_consecutives'    => min(5, $serieChu),
            'nb_matieres_danger'           => min(6, $nbMatieresDanger),
            'variance_notes'               => round(min(25.0, $variance), 2),
            'correlation_absence_note'     => round($correlationAbsNote, 3),
            'score_ews_normalise'          => round(($diagnostic ?? 0) / 100, 3),
        ];
    }

    private function calculerProbabilite(array $features): float
    {
        $somme = self::COEFFICIENTS['intercept'];
        foreach (self::COEFFICIENTS as $feature => $coeff) {
            if ($feature === 'intercept') continue;
            $somme += $coeff * ($features[$feature] ?? 0.0);
        }

        $proba = 1.0 / (1.0 + exp(-$somme));
        return round($proba * 100.0, 2);
    }

    private function ajusterHorizon(float $probabilite, string $horizon): float
    {
        $facteur = match ($horizon) {
            '4_semaines'    => 1.0,
            'fin_trimestre' => 0.85,
            'fin_annee'     => 0.70,
            default         => 1.0,
        };

        return 50.0 + ($probabilite - 50.0) * $facteur;
    }

    private function calculerConfiance(array $features, float $probabilite): float
    {
        $confiance = 95.0;

        if ($features['nb_notes_4sem'] < 3) $confiance -= 25.0;
        if ($features['nb_notes_4sem'] < 6) $confiance -= 10.0;
        if ($features['nb_absences_4sem'] === 0 && $features['nb_notes_4sem'] < 5) $confiance -= 5.0;

        $distance50 = abs($probabilite - 50.0);
        if ($distance50 < 10.0) $confiance -= 15.0;
        if ($distance50 < 5.0)  $confiance -= 10.0;

        return max(20.0, min(98.0, $confiance));
    }

    private function classifierRisque(float $probabilite): string
    {
        return match (true) {
            $probabilite < self::SEUIL_FAIBLE => 'faible',
            $probabilite < self::SEUIL_MODERE => 'modere',
            $probabilite < self::SEUIL_ELEVE  => 'eleve',
            default                           => 'critique',
        };
    }

    private function expliquerPrediction(array $features, float $probabilite): array
    {
        $facteurs = [];

        if ($features['tendance_4sem'] < -1.0) {
            $pts = abs(round($features['tendance_4sem'], 1));
            $facteurs[] = [
                'facteur'   => 'chute_rapide',
                'poids'     => round(abs(self::COEFFICIENTS['tendance_4sem'] * abs($features['tendance_4sem'])) / 5.0, 2),
                'direction' => 'negatif',
                'label'     => "Chute de {$pts} pts/semaine sur 4 semaines",
                'icone'     => '📉',
            ];
        }

        if ($features['nb_absences_justifiees_ratio'] > 0.3) {
            $nbNonJust = $features['nb_absences_4sem'] - $features['nb_absences_justifiees'];
            $facteurs[] = [
                'facteur'   => 'absences_non_justifiees',
                'poids'     => round(self::COEFFICIENTS['nb_absences_justifiees_ratio'] * $features['nb_absences_justifiees_ratio'] / 3.0, 2),
                'direction' => 'negatif',
                'label'     => "{$nbNonJust} absence(s) non justifiée(s) en 4 semaines",
                'icone'     => '🚫',
            ];
        }

        if ($features['nb_matieres_danger'] >= 1) {
            $facteurs[] = [
                'facteur'   => 'matieres_en_danger',
                'poids'     => round(self::COEFFICIENTS['nb_matieres_danger'] * $features['nb_matieres_danger'] / 4.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['nb_matieres_danger']} matière(s) avec moyenne < 8/20",
                'icone'     => '⚠️',
            ];
        }

        if ($features['nb_billets_4sem'] >= 1) {
            $facteurs[] = [
                'facteur'   => 'billets_comportement',
                'poids'     => round(self::COEFFICIENTS['nb_billets_4sem'] * $features['nb_billets_4sem'] / 3.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['nb_billets_4sem']} billet(s) comportement ce mois",
                'icone'     => '📋',
            ];
        }

        if ($features['serie_chutes_consecutives'] >= 2) {
            $facteurs[] = [
                'facteur'   => 'serie_chutes',
                'poids'     => round(self::COEFFICIENTS['serie_chutes_consecutives'] * $features['serie_chutes_consecutives'] / 3.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['serie_chutes_consecutives']} évaluations consécutives en baisse",
                'icone'     => '🔻',
            ];
        }

        if ($features['correlation_absence_note'] > 0.5) {
            $facteurs[] = [
                'facteur'   => 'correlation_absence_echec',
                'poids'     => round(self::COEFFICIENTS['correlation_absence_note'] * $features['correlation_absence_note'] / 1.5, 2),
                'direction' => 'negatif',
                'label'     => "Corrélation détectée : absences → baisse de notes",
                'icone'     => '🔗',
            ];
        }

        if ($features['pct_notes_sous_moy'] > 0.5 && count($facteurs) < 5) {
            $pct = round($features['pct_notes_sous_moy'] * 100);
            $facteurs[] = [
                'facteur'   => 'majorite_notes_faibles',
                'poids'     => round(self::COEFFICIENTS['pct_notes_sous_moy'] * $features['pct_notes_sous_moy'] / 2.0, 2),
                'direction' => 'negatif',
                'label'     => "{$pct}% des notes sous la moyenne (10/20)",
                'icone'     => '📊',
            ];
        }

        usort($facteurs, fn($a, $b) => $b['poids'] <=> $a['poids']);

        return array_slice($facteurs, 0, 5);
    }

    private function genererRecommandations(
        string $niveauRisque,
        array  $features,
        array  $facteurs
    ): array {
        $recs = [];
        $p    = 1;

        if ($niveauRisque === 'critique') {
            $recs[] = ['priorite' => $p++, 'type' => 'convocation', 'urgence' => 'immediate',
                'label' => 'Convoquer les parents sous 24h — Situation critique',
                'delai' => '24h'];
            $recs[] = ['priorite' => $p++, 'type' => 'conseil_classe', 'urgence' => 'urgent',
                'label' => 'Convoquer un conseil de classe exceptionnel',
                'delai' => '48h'];
        }

        if (in_array($niveauRisque, ['critique', 'eleve'])) {
            $recs[] = ['priorite' => $p++, 'type' => 'soutien_intensif', 'urgence' => 'urgent',
                'label' => 'Proposer un programme de soutien intensif',
                'delai' => '1 semaine'];
        }

        if ($features['nb_absences_justifiees_ratio'] > 0.3) {
            $recs[] = ['priorite' => $p++, 'type' => 'gestion_absences', 'urgence' => 'modere',
                'label' => 'Analyser les causes des absences — Entretien élève',
                'delai' => '1 semaine'];
        }

        if ($features['nb_matieres_danger'] >= 2) {
            $recs[] = ['priorite' => $p++, 'type' => 'soutien_matiere', 'urgence' => 'modere',
                'label' => "Soutien ciblé sur {$features['nb_matieres_danger']} matières en difficulté",
                'delai' => '2 semaines'];
        }

        if ($features['nb_billets_4sem'] >= 2) {
            $recs[] = ['priorite' => $p++, 'type' => 'suivi_comportement', 'urgence' => 'modere',
                'label' => "Entretien comportemental avec l'élève + référent",
                'delai' => '1 semaine'];
        }

        if ($niveauRisque === 'modere') {
            $recs[] = ['priorite' => $p++, 'type' => 'suivi_regulier', 'urgence' => 'surveillance',
                'label' => 'Suivi hebdomadaire — Réévaluation dans 2 semaines',
                'delai' => '2 semaines'];
        }

        if (empty($recs)) {
            $recs[] = ['priorite' => 1, 'type' => 'surveillance', 'urgence' => 'faible',
                'label' => 'Continuer la surveillance hebdomadaire standard',
                'delai' => 'continu'];
        }

        return $recs;
    }

    private function genererResume(string $niveauRisque, float $probabilite, array $facteurs): string
    {
        $niveauLabel = match ($niveauRisque) {
            'faible'   => 'Risque faible',
            'modere'   => 'Risque modéré',
            'eleve'    => 'Risque élevé',
            'critique' => 'Risque critique',
            default    => 'Risque inconnu',
        };

        $proba     = round($probabilite);
        $principal = $facteurs[0]['label'] ?? 'Données insuffisantes';

        return "{$niveauLabel} ({$proba}%). Facteur principal : {$principal}.";
    }

    private function persisterPrediction(
        string $eleveId,
        string $tenantId,
        float  $probabilite,
        float  $confiance,
        string $horizon,
        string $niveauRisque,
        array  $features,
        array  $facteurs,
        array  $recommandations
    ): string {
        $id = (string) Str::uuid();

        DB::table('predictions_echec')->insert([
            'id'                => $id,
            'tenant_id'         => $tenantId,
            'eleve_id'          => $eleveId,
            'probabilite_echec' => $probabilite,
            'confiance'         => $confiance,
            'horizon'           => $horizon,
            'niveau_risque'     => $niveauRisque,
            'features'          => json_encode($features),
            'facteurs_risque'   => json_encode($facteurs),
            'recommandations'   => json_encode($recommandations),
            'moteur_version'    => 'logistique_v1',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return $id;
    }

    private function calculerTendanceLineaire($notes): float
    {
        if ($notes->count() < 2) return 0.0;

        $n    = $notes->count();
        $xs   = range(0, $n - 1);
        $ys   = $notes->pluck('note')->toArray();
        $moyX = array_sum($xs) / $n;
        $moyY = array_sum($ys) / $n;

        $num = $den = 0.0;
        foreach ($xs as $i => $x) {
            $num += ($x - $moyX) * ($ys[$i] - $moyY);
            $den += ($x - $moyX) ** 2;
        }

        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    private function calculerChuteMaximale($notes): float
    {
        if ($notes->count() < 2) return 0.0;

        $vals     = $notes->pluck('note')->toArray();
        $chuteMax = 0.0;

        for ($i = 1; $i < count($vals); $i++) {
            $diff = $vals[$i] - $vals[$i - 1];
            if ($diff < $chuteMax) $chuteMax = $diff;
        }

        return round($chuteMax, 2);
    }

    private function calculerVariance($notes): float
    {
        if ($notes->count() < 2) return 0.0;

        $vals = $notes->pluck('note')->toArray();
        $moy  = array_sum($vals) / count($vals);
        $v    = array_sum(array_map(fn($x) => ($x - $moy) ** 2, $vals)) / count($vals);

        return round($v, 3);
    }

    private function calculerSerieChutes($notes): int
    {
        if ($notes->count() < 2) return 0;

        $vals     = $notes->pluck('note')->toArray();
        $maxSerie = $serie = 0;

        for ($i = 1; $i < count($vals); $i++) {
            if ($vals[$i] < $vals[$i - 1]) {
                $serie++;
                $maxSerie = max($maxSerie, $serie);
            } else {
                $serie = 0;
            }
        }

        return $maxSerie;
    }

    private function safeQuery(callable $query, mixed $default): mixed
    {
        try {
            return DB::transaction(fn() => $query());
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function fallbackEWS(string $eleveId, string $horizon): array
    {
        $score = (int) ($this->safeQuery(fn() => DB::table('diagnostics_eleves')
            ->where('eleve_id', $eleveId)->value('score_risque'), 30) ?? 30);

        return [
            'prediction_id'   => null,
            'eleve_id'        => $eleveId,
            'probabilite'     => (float) $score,
            'confiance'       => 40.0,
            'horizon'         => $horizon,
            'niveau_risque'   => $score >= 70 ? 'critique' : ($score >= 50 ? 'eleve' : ($score >= 25 ? 'modere' : 'faible')),
            'facteurs_risque' => [['facteur' => 'score_ews', 'label' => "Score EWS: {$score}/100", 'icone' => '📊']],
            'recommandations' => [['priorite' => 1, 'type' => 'analyse', 'label' => 'Données insuffisantes — Analyser manuellement']],
            'moteur'          => 'fallback_ews',
            'resume'          => "Données insuffisantes pour la prédiction IA. Score EWS: {$score}/100.",
        ];
    }
}
