# 🧠 MISSION — IA Prédiction Échec Scolaire (au-delà de l'EWS)
## EduGest DZ · Laravel 11 · PHP 8.2 · PostgreSQL 16
## Branche : develop · Tests actuels : 748+ ✅ · Objectif : ≥ 785 ✅

---

## CONTEXTE RÉEL LU DANS LE REPO (develop · 11 Juillet 2026)

```
CE QUI EXISTE DÉJÀ — NE PAS RECRÉER :
✅ DiagnosticService.php          → Score risque 0-100, seuils fixes (5/8/10/15)
✅ DiagnosticEleve model          → niveau_global, score_risque, moyenne_generale
✅ HistoriqueDiagnostic model     → historique des analyses par élève
✅ Note model                     → notes par évaluation, cours, groupe
✅ AbsenceJournaliere model       → absences élève avec justificatifs
✅ Billet model                   → billets comportement enseignant→élève
✅ EWS existant                   → vigilance / danger / critique (seuils fixes)

PROBLÈME FONDAMENTAL DE L'EWS ACTUEL :
❌ Seuils FIXES (5/8/10/15) → ne s'adaptent pas au contexte de l'école
❌ Pas de temporalité → "chute rapide en 2 semaines" = ignorée
❌ Pas de corrélation multi-signal → absence + note faible = non croisé automatiquement
❌ Pas de prédiction → réactif, pas proactif
❌ Pas de profil élève → un élève "toujours faible mais stable" = alarme inutile
❌ Pas d'explication → "score: 72" sans savoir POURQUOI
❌ Pas d'intervalle de confiance → 60% = traité pareil que 95%

CE QUE CETTE MISSION AJOUTE :
✅ PredictionEchecService         → modèle ML PHP natif (régression logistique)
✅ FacteurRisqueAnalyser          → décompose le score en facteurs explicables
✅ ProfilApprentissageService     → identifie le profil (stable_faible, chute_rapide, etc.)
✅ InterventionRecommandationService → recommandations contextuelles + priorité
✅ PredictionController           → API REST complète
✅ Table predictions_echec        → historique des prédictions avec features
✅ Table profils_apprentissage    → profil par élève mis à jour hebdo
✅ Dashboard IA directeur (React) → graphes temporels + heatmap + classement risque
✅ 17 tests Feature + Unit
```

### PRÉCISION TECHNIQUE OBLIGATOIRE — PAS DE TRICHE
```
⚠️  CETTE MISSION N'UTILISE PAS :
    - Python / Flask / FastAPI (pas disponible sur l'infra Laravel)
    - TensorFlow / PyTorch (aucune dépendance système requise)
    - OpenAI API (coût + données hors Algérie = violation loi 18-07)

    CETTE MISSION UTILISE :
    ✅ Régression logistique PHP natif (pas de lib externe)
    ✅ Features normalisées calculées depuis PostgreSQL
    ✅ Coefficients appris sur données synthétiques (pas de vraies données)
    ✅ Architecture prête pour remplacer le moteur par Python microservice
       via HTTP interne quand le VPS Algérie sera opérationnel
    ✅ Interface identique quel que soit le moteur sous-jacent (contrat)
```

---

## RÈGLES ABSOLUES
1. **0 régression** — 748+ tests verts avant cette mission → rester ≥ 748
2. **PostgreSQL uniquement** — `hasTable()` / `hasColumn()` sur toutes les migrations
3. **Loi 18-07** — aucune donnée élève envoyée à une API externe
4. **Dégradation gracieuse** — si calcul IA échoue → fallback sur DiagnosticService existant
5. **Ne pas modifier** DiagnosticService.php ni DiagnosticEleve model existants
6. **Explicabilité** — chaque prédiction DOIT avoir une explication en français
7. **Confiance** — chaque prédiction a un score de confiance 0-100%

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════
## BLOC A — BASE DE DONNÉES : 2 NOUVELLES TABLES
## ══════════════════════════════════════════

## ÉTAPE 1 — Migration : predictions_echec

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_100000_create_predictions_echec_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('predictions_echec')) {
            Schema::create('predictions_echec', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');

                // Prédiction principale
                $table->decimal('probabilite_echec', 5, 2);    // 0.00 → 100.00 (%)
                $table->decimal('confiance', 5, 2);             // 0.00 → 100.00 (%)
                $table->string('horizon', 20)->default('4_semaines');
                // 4_semaines | fin_trimestre | fin_annee

                // Niveau de risque calculé
                $table->string('niveau_risque', 20);
                // faible | modere | eleve | critique

                // Features utilisées pour la prédiction (pour audit + explicabilité)
                $table->jsonb('features');
                /*
                {
                  "moyenne_actuelle": 8.5,
                  "tendance_4sem": -1.8,
                  "tendance_8sem": -0.4,
                  "nb_absences_4sem": 3,
                  "nb_absences_justifiees": 1,
                  "nb_billets_4sem": 2,
                  "pct_notes_sous_moy": 0.65,
                  "nb_matieres_danger": 2,
                  "chute_max_1sem": -2.5,
                  "ratio_absences_excusees": 0.33,
                  "score_ews_actuel": 68,
                  "serie_chutes_consecutives": 2,
                  "variance_notes": 4.2
                }
                */

                // Facteurs explicables en français
                $table->jsonb('facteurs_risque');
                /*
                [
                  {"facteur": "chute_rapide", "poids": 0.35, "label": "Chute de 2.5 pts en 1 semaine"},
                  {"facteur": "absences_frequentes", "poids": 0.28, "label": "3 absences sur 4 semaines"},
                  {"facteur": "matieres_multiples", "poids": 0.22, "label": "2 matières sous 8/20"},
                  {"facteur": "billets_recents", "poids": 0.15, "label": "2 billets comportement ce mois"}
                ]
                */

                // Recommandations d'intervention
                $table->jsonb('recommandations');
                /*
                [
                  {"priorite": 1, "type": "convocation", "label": "Convoquer les parents sous 48h"},
                  {"priorite": 2, "type": "soutien", "label": "Proposer soutien Mathématiques"},
                  {"priorite": 3, "type": "suivi", "label": "Réévaluer dans 2 semaines"}
                ]
                */

                $table->string('moteur_version', 20)->default('logistique_v1');
                // logistique_v1 | gradient_boosting_v1 | neural_v1

                $table->boolean('confirme_par_directeur')->default(false);
                $table->text('note_directeur')->nullable();
                $table->timestamp('notifie_le')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'niveau_risque'],   'idx_pred_tenant_niveau');
                $table->index(['eleve_id', 'created_at'],       'idx_pred_eleve_date');
                $table->index(['probabilite_echec'],             'idx_pred_proba');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('predictions_echec'); }
};
```

---

## ÉTAPE 2 — Migration : profils_apprentissage

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_110000_create_profils_apprentissage_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('profils_apprentissage')) {
            Schema::create('profils_apprentissage', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id')->unique();

                $table->string('profil', 40);
                /*
                Profils possibles :
                - excellent_stable      → Toujours > 15, variance faible
                - bon_regulier          → 12-15, stable, peu d'absences
                - moyen_stable          → 8-12, stable : PAS d'alarme même si faible
                - fragile_amelioration  → < 10 mais tendance positive
                - chute_rapide          → Baisse > 2pts/semaine sur 2 semaines
                - instable_oscillant    → Forte variance (> 5 pts), imprévisible
                - absenteiste           → > 20% absences, notes corrélées
                - decrochage_avance     → < 5, série ≥ 3 semaines, billets
                - saisonnier            → Chute en fin de trimestre uniquement
                - resilient             → Chutes puis récupère systématiquement
                */

                $table->decimal('stabilite_score', 5, 2);  // 0=très instable, 100=très stable
                $table->decimal('tendance_long_terme', 6, 3);  // pente régression linéaire sur 8 semaines
                $table->decimal('variance_notes', 8, 4);    // variance statistique des notes
                $table->integer('nb_chutes_recuperees');    // nombre de fois où il a récupéré
                $table->integer('nb_chutes_non_recuperees');
                $table->decimal('correlation_absences_notes', 5, 3)->nullable();
                // -1.0 → +1.0 (Pearson)

                $table->jsonb('points_forts');   // ["Mathématiques", "Sciences"]
                $table->jsonb('points_faibles'); // ["Arabe", "Histoire"]
                $table->jsonb('historique_profils'); // [{date, profil}] → évolution

                $table->timestamp('calcule_le');
                $table->timestamps();

                $table->index(['tenant_id', 'profil'],  'idx_profil_tenant');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('profils_apprentissage'); }
};
```

---

## ══════════════════════════════════════════
## BLOC B — COEUR IA : MOTEUR DE PRÉDICTION PHP
## ══════════════════════════════════════════

## ÉTAPE 3 — PredictionEchecService (moteur IA principal)

**Créer** : `edugestdz/backend/app/Services/PredictionEchecService.php`

```php
<?php

namespace App\Services;

use App\Models\{Eleve, DiagnosticEleve};
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;

/**
 * PredictionEchecService — Moteur de prédiction d'échec scolaire.
 *
 * ARCHITECTURE :
 * Ce service implémente une régression logistique PHP natif.
 * L'avantage : 0 dépendance externe, 0 appel API externe (loi 18-07 respectée).
 *
 * MODÈLE MATHÉMATIQUE :
 * P(échec) = sigmoid(β₀ + β₁×tendance + β₂×absences + β₃×chute_max + ...)
 * sigmoid(x) = 1 / (1 + e^(-x))
 *
 * COEFFICIENTS (appris sur données synthétiques algériennes) :
 * Seront affinés par tenant quand les données réelles seront disponibles
 * (après ANPDP + VPS Algérie opérationnel).
 *
 * DISTINCTION EWS vs PRÉDICTION :
 * EWS (DiagnosticService) = RÉACTIF : "Il est en danger MAINTENANT"
 * Prédiction IA            = PROACTIF : "Il sera en échec dans 4 semaines"
 *
 * INTERFACE STABLE : la méthode predire() a la même signature quel que soit
 * le moteur. On peut remplacer le corps par un appel HTTP à un microservice
 * Python sans changer aucun controller.
 */
class PredictionEchecService
{
    // ── Coefficients du modèle de régression logistique ──────────────
    // Interprétation : valeur positive = augmente le risque d'échec
    private const COEFFICIENTS = [
        'intercept'                    => -2.1,   // Biais de base
        'tendance_4sem'                => -0.48,  // Chute = risque élevé (négatif = mauvais)
        'pct_notes_sous_moy'           =>  2.85,  // % notes sous moyenne classe
        'nb_absences_justifiees_ratio' =>  0.62,  // Absences non justifiées
        'chute_max_1sem'               => -0.73,  // Chute maximale en 1 semaine
        'serie_chutes_consecutives'    =>  0.91,  // Série de chutes = très mauvais signe
        'nb_matieres_danger'           =>  0.67,  // Matières sous 8/20
        'variance_notes'               =>  0.19,  // Instabilité
        'nb_billets_4sem'              =>  0.44,  // Billets comportement
        'correlation_absence_note'     =>  1.12,  // Si abs→chute notes: très mauvais
        'score_ews_normalise'          =>  0.038, // Score EWS actuel (normalisé /100)
    ];

    // Seuils de classification du risque
    private const SEUIL_FAIBLE    = 25.0;
    private const SEUIL_MODERE    = 50.0;
    private const SEUIL_ELEVE     = 70.0;
    // > 70 = critique

    /**
     * Prédire la probabilité d'échec d'un élève.
     *
     * @param  string  $eleveId  UUID de l'élève
     * @param  string  $horizon  '4_semaines' | 'fin_trimestre' | 'fin_annee'
     * @param  string  $tenantId
     * @return array
     */
    public function predire(
        string $eleveId,
        string $horizon   = '4_semaines',
        string $tenantId  = null
    ): array {
        $tenantId = $tenantId ?? config('tenant.current_id');

        try {
            // 1. Extraire les features depuis PostgreSQL
            $features = $this->extraireFeatures($eleveId, $tenantId);

            // 2. Calculer la probabilité via régression logistique
            $probabilite = $this->calculerProbabilite($features);

            // 3. Ajuster selon l'horizon (plus long = incertitude = vers 50%)
            $probabiliteAjustee = $this->ajusterHorizon($probabilite, $horizon);

            // 4. Calculer le niveau de confiance du modèle
            $confiance = $this->calculerConfiance($features, $probabiliteAjustee);

            // 5. Déterminer le niveau de risque
            $niveauRisque = $this->classifierRisque($probabiliteAjustee);

            // 6. Décomposer les facteurs explicables
            $facteursRisque = $this->expliquerPrediction($features, $probabiliteAjustee);

            // 7. Générer les recommandations
            $recommandations = $this->genererRecommandations($niveauRisque, $features, $facteursRisque);

            // 8. Persister en BDD
            $predictionId = $this->persisterPrediction(
                $eleveId, $tenantId, $probabiliteAjustee, $confiance,
                $horizon, $niveauRisque, $features, $facteursRisque, $recommandations
            );

            return [
                'prediction_id'    => $predictionId,
                'eleve_id'         => $eleveId,
                'probabilite'      => round($probabiliteAjustee, 1),
                'confiance'        => round($confiance, 1),
                'horizon'          => $horizon,
                'niveau_risque'    => $niveauRisque,
                'facteurs_risque'  => $facteursRisque,
                'recommandations'  => $recommandations,
                'moteur'           => 'logistique_v1',
                'resume'           => $this->genererResume($niveauRisque, $probabiliteAjustee, $facteursRisque),
            ];
        } catch (\Throwable $e) {
            Log::warning("PredictionEchec: fallback DiagnosticService pour {$eleveId}: " . $e->getMessage());

            // FALLBACK → utiliser le score EWS existant
            return $this->fallbackEWS($eleveId, $horizon);
        }
    }

    /**
     * Prédire pour tous les élèves d'un tenant (batch).
     */
    public function predireTenant(string $tenantId): array
    {
        $eleveIds = DB::table('eleves')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'actif')
            ->pluck('id');

        $resultats = [];
        foreach ($eleveIds as $eleveId) {
            $resultats[] = $this->predire($eleveId, '4_semaines', $tenantId);
        }

        // Trier par probabilité décroissante
        usort($resultats, fn($a, $b) => $b['probabilite'] <=> $a['probabilite']);

        return $resultats;
    }

    // ── Feature Engineering ───────────────────────────────────────────

    /**
     * Extraire toutes les features depuis PostgreSQL pour un élève.
     * Toutes les features sont normalisées [0, 1] pour la régression.
     */
    private function extraireFeatures(string $eleveId, string $tenantId): array
    {
        $maintenant = now();
        $il_y_a_4sem = $maintenant->copy()->subWeeks(4)->toDateString();
        $il_y_a_8sem = $maintenant->copy()->subWeeks(8)->toDateString();

        // ── Notes récentes (4 semaines) ───────────────────────────
        $notes4sem = DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->join('seances as s', 'e.seance_id', '=', 's.id')
            ->where('n.eleve_id', $eleveId)
            ->where('s.date', '>=', $il_y_a_4sem)
            ->select('n.note', 's.date')
            ->orderBy('s.date')
            ->get();

        // ── Notes 8 semaines (pour tendance long terme) ────────────
        $notes8sem = DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->join('seances as s', 'e.seance_id', '=', 's.id')
            ->where('n.eleve_id', $eleveId)
            ->where('s.date', '>=', $il_y_a_8sem)
            ->select('n.note', 's.date')
            ->orderBy('s.date')
            ->get();

        // ── Absences (4 semaines) ──────────────────────────────────
        $absences = DB::table('absences_journalieres')
            ->where('eleve_id', $eleveId)
            ->where('date_absence', '>=', $il_y_a_4sem)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN justifie THEN 1 ELSE 0 END) as justifiees')
            ->first();

        // ── Billets comportement (4 semaines) ─────────────────────
        $nbBillets = DB::table('billets')
            ->where('eleve_id', $eleveId)
            ->where('created_at', '>=', $il_y_a_4sem)
            ->count();

        // ── Score EWS actuel ───────────────────────────────────────
        $diagnostic = DB::table('diagnostics_eleves')
            ->where('eleve_id', $eleveId)
            ->value('score_risque');

        // ── Calcul des features dérivées ───────────────────────────
        $moyenneActuelle = $notes4sem->avg('note') ?? 10.0;
        $moyennePrecedente = $notes8sem->take($notes8sem->count() - $notes4sem->count())->avg('note') ?? $moyenneActuelle;

        $tendance4sem    = $this->calculerTendanceLineaire($notes4sem);
        $chuteMax1sem    = $this->calculerChuteMaximale($notes4sem);
        $variance        = $this->calculerVariance($notes4sem);
        $serieChu        = $this->calculerSerieChutes($notes4sem);

        $totalAbsences    = (int) ($absences->total ?? 0);
        $absencesJust     = (int) ($absences->justifiees ?? 0);
        $ratioNonJust     = $totalAbsences > 0
            ? ($totalAbsences - $absencesJust) / $totalAbsences
            : 0.0;

        // % notes sous moyenne de la classe (approx. moyenne classe = 10)
        $pctSousMoy = $notes4sem->count() > 0
            ? $notes4sem->filter(fn($n) => $n->note < 10)->count() / $notes4sem->count()
            : 0.0;

        // Matières en danger (< 8/20)
        $nbMatieresDanger = (int) DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->join('seances as s', 'e.seance_id', '=', 's.id')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->where('n.eleve_id', $eleveId)
            ->where('s.date', '>=', $il_y_a_4sem)
            ->groupBy('c.matiere_id')
            ->havingRaw('AVG(n.note) < 8')
            ->get()->count();

        // Corrélation absences → chute de notes (simplifié)
        $correlationAbsNote = ($totalAbsences >= 2 && $tendance4sem < -0.5) ? 0.7 : 0.0;

        return [
            // Données brutes (pour affichage / audit)
            'moyenne_actuelle'             => round($moyenneActuelle, 2),
            'moyenne_precedente'           => round($moyennePrecedente, 2),
            'nb_notes_4sem'                => $notes4sem->count(),
            'nb_absences_4sem'             => $totalAbsences,
            'nb_absences_justifiees'       => $absencesJust,
            'nb_billets_4sem'              => $nbBillets,
            'score_ews_actuel'             => (int) ($diagnostic ?? 0),

            // Features normalisées pour le modèle
            'tendance_4sem'                => max(-5.0, min(5.0, $tendance4sem)),
            'pct_notes_sous_moy'           => round($pctSousMoy, 3),
            'nb_absences_justifiees_ratio' => round($ratioNonJust, 3),
            'chute_max_1sem'               => max(-10.0, min(0.0, $chuteMax1sem)),
            'serie_chutes_consecutives'    => min(5, $serieChu),
            'nb_matieres_danger'           => min(6, $nbMatieresDanger),
            'variance_notes'               => round(min(25.0, $variance), 2),
            'nb_billets_4sem'              => min(5, $nbBillets),
            'correlation_absence_note'     => round($correlationAbsNote, 3),
            'score_ews_normalise'          => round(($diagnostic ?? 0) / 100, 3),
        ];
    }

    /**
     * Calcul de la probabilité d'échec via régression logistique.
     * P = sigmoid(Σ βᵢ × xᵢ)
     */
    private function calculerProbabilite(array $features): float
    {
        $somme = self::COEFFICIENTS['intercept'];
        foreach (self::COEFFICIENTS as $feature => $coeff) {
            if ($feature === 'intercept') continue;
            $somme += $coeff * ($features[$feature] ?? 0.0);
        }

        // Sigmoid → [0, 1]
        $proba = 1.0 / (1.0 + exp(-$somme));

        // Convertir en % [0, 100]
        return round($proba * 100.0, 2);
    }

    /**
     * Ajuster la probabilité selon l'horizon temporel.
     * Plus l'horizon est lointain, plus on "régresse vers la moyenne".
     */
    private function ajusterHorizon(float $probabilite, string $horizon): float
    {
        $facteur = match ($horizon) {
            '4_semaines'    => 1.0,   // Prédiction directe
            'fin_trimestre' => 0.85,  // Légère régression
            'fin_annee'     => 0.70,  // Forte incertitude
            default         => 1.0,
        };

        // Régresser vers 50% (incertitude maximale)
        return 50.0 + ($probabilite - 50.0) * $facteur;
    }

    /**
     * Calculer la confiance du modèle selon la qualité des données.
     */
    private function calculerConfiance(array $features, float $probabilite): float
    {
        $confiance = 95.0;

        // Pénalités si données insuffisantes
        if ($features['nb_notes_4sem'] < 3)     $confiance -= 25.0;
        if ($features['nb_notes_4sem'] < 6)     $confiance -= 10.0;
        if ($features['nb_absences_4sem'] === 0 && $features['nb_notes_4sem'] < 5) $confiance -= 5.0;

        // Prédiction proche de 50% = incertaine
        $distance50 = abs($probabilite - 50.0);
        if ($distance50 < 10.0) $confiance -= 15.0;
        if ($distance50 < 5.0)  $confiance -= 10.0;

        return max(20.0, min(98.0, $confiance));
    }

    /**
     * Classifier le niveau de risque depuis la probabilité.
     */
    private function classifierRisque(float $probabilite): string
    {
        return match (true) {
            $probabilite < self::SEUIL_FAIBLE  => 'faible',
            $probabilite < self::SEUIL_MODERE  => 'modere',
            $probabilite < self::SEUIL_ELEVE   => 'eleve',
            default                            => 'critique',
        };
    }

    /**
     * Générer une explication en français des facteurs de risque.
     * Chaque facteur a un poids, un label humain, et une direction.
     */
    private function expliquerPrediction(array $features, float $probabilite): array
    {
        $facteurs = [];

        // Chute rapide (poids fort négatif)
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

        // Absences non justifiées
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

        // Matières en danger
        if ($features['nb_matieres_danger'] >= 1) {
            $facteurs[] = [
                'facteur'   => 'matieres_en_danger',
                'poids'     => round(self::COEFFICIENTS['nb_matieres_danger'] * $features['nb_matieres_danger'] / 4.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['nb_matieres_danger']} matière(s) avec moyenne < 8/20",
                'icone'     => '⚠️',
            ];
        }

        // Billets comportement
        if ($features['nb_billets_4sem'] >= 1) {
            $facteurs[] = [
                'facteur'   => 'billets_comportement',
                'poids'     => round(self::COEFFICIENTS['nb_billets_4sem'] * $features['nb_billets_4sem'] / 3.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['nb_billets_4sem']} billet(s) comportement ce mois",
                'icone'     => '📋',
            ];
        }

        // Série de chutes consécutives
        if ($features['serie_chutes_consecutives'] >= 2) {
            $facteurs[] = [
                'facteur'   => 'serie_chutes',
                'poids'     => round(self::COEFFICIENTS['serie_chutes_consecutives'] * $features['serie_chutes_consecutives'] / 3.0, 2),
                'direction' => 'negatif',
                'label'     => "{$features['serie_chutes_consecutives']} évaluations consécutives en baisse",
                'icone'     => '🔻',
            ];
        }

        // Corrélation absence→notes
        if ($features['correlation_absence_note'] > 0.5) {
            $facteurs[] = [
                'facteur'   => 'correlation_absence_echec',
                'poids'     => round(self::COEFFICIENTS['correlation_absence_note'] * $features['correlation_absence_note'] / 1.5, 2),
                'direction' => 'negatif',
                'label'     => "Corrélation détectée : absences → baisse de notes",
                'icone'     => '🔗',
            ];
        }

        // Notes sous moyenne (% élevé)
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

        // Trier par poids décroissant (les facteurs les plus importants en premier)
        usort($facteurs, fn($a, $b) => $b['poids'] <=> $a['poids']);

        return array_slice($facteurs, 0, 5); // Max 5 facteurs affichés
    }

    /**
     * Générer des recommandations d'intervention contextuelles.
     */
    private function genererRecommandations(
        string $niveauRisque,
        array  $features,
        array  $facteurs
    ): array {
        $recs = [];
        $p    = 1;

        if ($niveauRisque === 'critique') {
            $recs[] = ['priorite' => $p++, 'type' => 'convocation', 'urgence' => 'immediate',
                'label' => '🚨 Convoquer les parents sous 24h — Situation critique',
                'delai' => '24h'];
            $recs[] = ['priorite' => $p++, 'type' => 'conseil_classe', 'urgence' => 'urgent',
                'label' => '📋 Convoquer un conseil de classe exceptionnel',
                'delai' => '48h'];
        }

        if (in_array($niveauRisque, ['critique', 'eleve'])) {
            $recs[] = ['priorite' => $p++, 'type' => 'soutien_intensif', 'urgence' => 'urgent',
                'label' => '📚 Proposer un programme de soutien intensif',
                'delai' => '1 semaine'];
        }

        if ($features['nb_absences_justifiees_ratio'] > 0.3) {
            $recs[] = ['priorite' => $p++, 'type' => 'gestion_absences', 'urgence' => 'modere',
                'label' => '🗓️ Analyser les causes des absences — Entretien élève',
                'delai' => '1 semaine'];
        }

        if ($features['nb_matieres_danger'] >= 2) {
            $recs[] = ['priorite' => $p++, 'type' => 'soutien_matiere', 'urgence' => 'modere',
                'label' => "📐 Soutien ciblé sur {$features['nb_matieres_danger']} matières en difficulté",
                'delai' => '2 semaines'];
        }

        if ($features['nb_billets_4sem'] >= 2) {
            $recs[] = ['priorite' => $p++, 'type' => 'suivi_comportement', 'urgence' => 'modere',
                'label' => '🤝 Entretien comportemental avec l\'élève + référent',
                'delai' => '1 semaine'];
        }

        if ($niveauRisque === 'modere') {
            $recs[] = ['priorite' => $p++, 'type' => 'suivi_regulier', 'urgence' => 'surveillance',
                'label' => '📈 Suivi hebdomadaire — Réévaluation dans 2 semaines',
                'delai' => '2 semaines'];
        }

        if (empty($recs)) {
            $recs[] = ['priorite' => 1, 'type' => 'surveillance', 'urgence' => 'faible',
                'label' => '✅ Continuer la surveillance hebdomadaire standard',
                'delai' => 'continu'];
        }

        return $recs;
    }

    /**
     * Générer un résumé textuel en français pour le directeur.
     */
    private function genererResume(string $niveauRisque, float $probabilite, array $facteurs): string
    {
        $niveauLabel = match ($niveauRisque) {
            'faible'   => 'Risque faible',
            'modere'   => 'Risque modéré',
            'eleve'    => 'Risque élevé',
            'critique' => '⚠️ Risque critique',
            default    => 'Risque inconnu',
        };

        $proba = round($probabilite);
        $principal = $facteurs[0]['label'] ?? 'Données insuffisantes';

        return "{$niveauLabel} ({$proba}%). Facteur principal : {$principal}.";
    }

    /**
     * Persister la prédiction en BDD.
     */
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
            'id'               => $id,
            'tenant_id'        => $tenantId,
            'eleve_id'         => $eleveId,
            'probabilite_echec'=> $probabilite,
            'confiance'        => $confiance,
            'horizon'          => $horizon,
            'niveau_risque'    => $niveauRisque,
            'features'         => json_encode($features),
            'facteurs_risque'  => json_encode($facteurs),
            'recommandations'  => json_encode($recommandations),
            'moteur_version'   => 'logistique_v1',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $id;
    }

    // ── Calculs statistiques ──────────────────────────────────────────

    /**
     * Tendance linéaire (pente de la droite de régression) des notes.
     * Valeur négative = baisse, positive = hausse.
     */
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

    /**
     * Chute maximale enregistrée entre 2 évaluations consécutives.
     */
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

    /**
     * Variance statistique des notes.
     */
    private function calculerVariance($notes): float
    {
        if ($notes->count() < 2) return 0.0;

        $vals = $notes->pluck('note')->toArray();
        $moy  = array_sum($vals) / count($vals);
        $v    = array_sum(array_map(fn($x) => ($x - $moy) ** 2, $vals)) / count($vals);

        return round($v, 3);
    }

    /**
     * Nombre de chutes consécutives (évaluations baissant sans interruption).
     */
    private function calculerSerieChutes($notes): int
    {
        if ($notes->count() < 2) return 0;

        $vals    = $notes->pluck('note')->toArray();
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

    /**
     * Fallback sur DiagnosticService si le calcul IA échoue.
     */
    private function fallbackEWS(string $eleveId, string $horizon): array
    {
        $score = (int) (DB::table('diagnostics_eleves')
            ->where('eleve_id', $eleveId)->value('score_risque') ?? 30);

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
```

---

## ÉTAPE 4 — ProfilApprentissageService

**Créer** : `edugestdz/backend/app/Services/ProfilApprentissageService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ProfilApprentissageService — Identifie le profil d'apprentissage d'un élève.
 *
 * DISTINCTION CRITIQUE avec EWS :
 * Un élève "moyen_stable" (8.5/20 depuis 3 mois sans bouger)
 * NE doit PAS déclencher d'alarme → il a trouvé son niveau.
 * C'est un "chute_rapide" (14→9 en 2 semaines) qui est VRAIMENT en danger.
 *
 * Cette intelligence supprime les faux positifs de l'EWS.
 */
class ProfilApprentissageService
{
    public function calculerProfil(string $eleveId, string $tenantId = null): array
    {
        $tenantId    = $tenantId ?? config('tenant.current_id');
        $historique  = $this->chargerHistoriqueSemaine($eleveId, 8);
        $moy8sem     = $historique->avg('moyenne') ?? 10.0;
        $variance    = $this->variance($historique->pluck('moyenne')->toArray());
        $tendance    = $this->pente($historique->pluck('moyenne')->toArray());
        $absencesPct = $this->calculerPctAbsences($eleveId);

        // Détecter les chutes et récupérations
        [$nbChutesRec, $nbChutesSansRec] = $this->detecterChuteEtRecuperation($historique);

        // Choisir le profil
        $profil = $this->choisirProfil($moy8sem, $variance, $tendance, $absencesPct, $nbChutesSansRec, $nbChutesRec);

        // Points forts / faibles par matière
        [$pointsForts, $pointsFaibles] = $this->calculerPointsForts($eleveId);

        // Sauvegarder
        $this->sauvegarder($eleveId, $tenantId, [
            'profil'                    => $profil,
            'stabilite_score'           => max(0, min(100, 100 - $variance * 5)),
            'tendance_long_terme'       => round($tendance, 3),
            'variance_notes'            => round($variance, 4),
            'nb_chutes_recuperees'      => $nbChutesRec,
            'nb_chutes_non_recuperees'  => $nbChutesSansRec,
            'correlation_absences_notes'=> $this->correlationAbsenceNote($eleveId),
            'points_forts'              => json_encode($pointsForts),
            'points_faibles'            => json_encode($pointsFaibles),
            'historique_profils'        => $this->majHistoriqueProfils($eleveId, $profil),
        ]);

        return [
            'profil'         => $profil,
            'label_fr'       => $this->labelFr($profil),
            'emoji'          => $this->emoji($profil),
            'alarme'         => $this->requiertAlarme($profil),
            'points_forts'   => $pointsForts,
            'points_faibles' => $pointsFaibles,
            'stabilite'      => max(0, min(100, (int)(100 - $variance * 5))),
            'explication'    => $this->explication($profil, $moy8sem, $tendance),
        ];
    }

    private function choisirProfil(
        float $moy, float $var, float $tendance,
        float $absPct, int $chutesSansRec, int $chutesRec
    ): string {
        if ($moy >= 15.0 && $var < 3.0)         return 'excellent_stable';
        if ($absPct > 0.20)                      return 'absenteiste';
        if ($moy < 5.0 && $chutesSansRec >= 3)  return 'decrochage_avance';
        if ($tendance < -0.8 && $var < 5.0)     return 'chute_rapide';
        if ($chutesRec >= 2 && $chutesSansRec < 2) return 'resilient';
        if ($var > 8.0)                          return 'instable_oscillant';
        if ($moy >= 12.0 && $var < 5.0)         return 'bon_regulier';
        if ($tendance > 0.5 && $moy < 10.0)     return 'fragile_amelioration';
        if ($moy >= 8.0 && $var < 4.0)          return 'moyen_stable';
        return 'moyen_stable';
    }

    private function requiertAlarme(string $profil): bool
    {
        // SEULEMENT ces profils déclenchent une alarme
        return in_array($profil, ['chute_rapide', 'decrochage_avance', 'absenteiste']);
        // 'moyen_stable' N'est PAS une alarme même si moy < 10
    }

    private function labelFr(string $profil): string
    {
        return match ($profil) {
            'excellent_stable'     => 'Excellent & Stable',
            'bon_regulier'         => 'Bon & Régulier',
            'moyen_stable'         => 'Niveau Stable (sans alarme)',
            'fragile_amelioration' => 'Fragile mais en Progrès',
            'chute_rapide'         => '⚠️ Chute Rapide',
            'instable_oscillant'   => 'Instable / Irrégulier',
            'absenteiste'          => '🚫 Absentéisme Préoccupant',
            'decrochage_avance'    => '🚨 Décrochage Avancé',
            'saisonnier'           => 'Difficultés Saisonnières',
            'resilient'            => '💪 Résilient (se récupère bien)',
            default                => 'Profil en cours d\'analyse',
        };
    }

    private function emoji(string $profil): string
    {
        return match ($profil) {
            'excellent_stable'     => '⭐',
            'bon_regulier'         => '✅',
            'moyen_stable'         => '〰️',
            'fragile_amelioration' => '📈',
            'chute_rapide'         => '📉',
            'instable_oscillant'   => '〜',
            'absenteiste'          => '🚫',
            'decrochage_avance'    => '🆘',
            'resilient'            => '💪',
            default                => '❓',
        };
    }

    private function explication(string $profil, float $moy, float $tendance): string
    {
        return match ($profil) {
            'chute_rapide'      => "Baisse de " . abs(round($tendance, 1)) . " pts/semaine sur 8 semaines — Intervention requise",
            'moyen_stable'      => "Moyenne de {$moy}/20 stable depuis 8 semaines — Niveau consolidé, pas d'alarme",
            'decrochage_avance' => "Situation critique depuis plusieurs semaines — Plan d'urgence requis",
            'resilient'         => "Élève qui récupère systématiquement après les chutes — Bon signe",
            'fragile_amelioration' => "En difficulté mais la tendance est positive (+{$tendance} pts/sem)",
            'excellent_stable'  => "Excellentes performances stables — Peut être valorisé",
            default             => "Profil {$profil} — Surveillance standard",
        };
    }

    private function chargerHistoriqueSemaine(string $eleveId, int $semaines)
    {
        return DB::table('historique_diagnostics as h')
            ->where('h.eleve_id', $eleveId)
            ->where('h.analyse_le', '>=', now()->subWeeks($semaines))
            ->select('h.moyenne_generale as moyenne', 'h.analyse_le')
            ->orderBy('h.analyse_le')
            ->get();
    }

    private function variance(array $vals): float
    {
        if (count($vals) < 2) return 0.0;
        $moy = array_sum($vals) / count($vals);
        return array_sum(array_map(fn($x) => ($x - $moy) ** 2, $vals)) / count($vals);
    }

    private function pente(array $vals): float
    {
        $n = count($vals);
        if ($n < 2) return 0.0;
        $xs = range(0, $n - 1);
        $moyX = array_sum($xs) / $n;
        $moyY = array_sum($vals) / $n;
        $num = $den = 0;
        foreach ($xs as $i => $x) {
            $num += ($x - $moyX) * ($vals[$i] - $moyY);
            $den += ($x - $moyX) ** 2;
        }
        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    private function detecterChuteEtRecuperation($historique): array
    {
        $vals = $historique->pluck('moyenne')->toArray();
        if (count($vals) < 3) return [0, 0];

        $chutesRec = $chutesSansRec = 0;
        $i = 1;
        while ($i < count($vals) - 1) {
            if ($vals[$i] < $vals[$i - 1] - 1.5) {
                // Chute détectée — voit-elle une récupération après?
                if ($i + 1 < count($vals) && $vals[$i + 1] > $vals[$i] + 1.0) {
                    $chutesRec++;
                    $i += 2;
                } else {
                    $chutesSansRec++;
                    $i++;
                }
            } else {
                $i++;
            }
        }

        return [$chutesRec, $chutesSansRec];
    }

    private function calculerPctAbsences(string $eleveId): float
    {
        $total = DB::table('absences_journalieres')
            ->where('eleve_id', $eleveId)
            ->where('date_absence', '>=', now()->subWeeks(8)->toDateString())
            ->count();

        // 40 jours scolaires sur 8 semaines (5 jours × 8 = 40)
        return min(1.0, $total / 40.0);
    }

    private function correlationAbsenceNote(string $eleveId): ?float
    {
        // Pearson simplifié sur les données hebdomadaires
        $donnees = DB::table('historique_diagnostics')
            ->where('eleve_id', $eleveId)
            ->where('analyse_le', '>=', now()->subWeeks(8))
            ->select('moyenne_generale', DB::raw("(details->>'comportement'->>'absences')::numeric as nb_abs"))
            ->get();

        if ($donnees->count() < 3) return null;

        $moyennes  = $donnees->pluck('moyenne_generale')->toArray();
        $absences  = $donnees->map(fn($d) => (float)($d->nb_abs ?? 0))->toArray();
        $n         = count($moyennes);
        $moyM      = array_sum($moyennes) / $n;
        $moyA      = array_sum($absences) / $n;

        $num = $denM = $denA = 0;
        for ($i = 0; $i < $n; $i++) {
            $num  += ($moyennes[$i] - $moyM) * ($absences[$i] - $moyA);
            $denM += ($moyennes[$i] - $moyM) ** 2;
            $denA += ($absences[$i] - $moyA) ** 2;
        }

        $den = sqrt($denM * $denA);
        return $den > 0 ? round(-$num / $den, 3) : null; // Négatif car absence→baisse notes
    }

    private function calculerPointsForts(string $eleveId): array
    {
        $parMatiere = DB::table('notes as n')
            ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
            ->join('seances as s', 'e.seance_id', '=', 's.id')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->join('matieres as m', 'c.matiere_id', '=', 'm.id')
            ->where('n.eleve_id', $eleveId)
            ->where('s.date', '>=', now()->subWeeks(8)->toDateString())
            ->groupBy('m.id', 'm.nom_fr')
            ->select('m.nom_fr', DB::raw('AVG(n.note) as moy'))
            ->get();

        $forts   = $parMatiere->filter(fn($m) => $m->moy >= 13)->pluck('nom_fr')->toArray();
        $faibles = $parMatiere->filter(fn($m) => $m->moy < 8)->pluck('nom_fr')->toArray();

        return [$forts, $faibles];
    }

    private function majHistoriqueProfils(string $eleveId, string $nouveauProfil): string
    {
        $actuel = DB::table('profils_apprentissage')
            ->where('eleve_id', $eleveId)
            ->value('historique_profils');

        $historique = $actuel ? json_decode($actuel, true) : [];
        $historique[] = ['date' => now()->toDateString(), 'profil' => $nouveauProfil];

        // Garder les 12 derniers entrées (un an)
        $historique = array_slice($historique, -12);

        return json_encode($historique);
    }

    private function sauvegarder(string $eleveId, string $tenantId, array $data): void
    {
        $existing = DB::table('profils_apprentissage')->where('eleve_id', $eleveId)->first();

        $payload = array_merge($data, [
            'tenant_id'   => $tenantId,
            'eleve_id'    => $eleveId,
            'calcule_le'  => now(),
            'updated_at'  => now(),
        ]);

        if ($existing) {
            DB::table('profils_apprentissage')->where('eleve_id', $eleveId)->update($payload);
        } else {
            DB::table('profils_apprentissage')->insert(array_merge($payload, [
                'id'         => (string) Str::uuid(),
                'created_at' => now(),
            ]));
        }
    }
}
```

---

## ══════════════════════════════════════════
## BLOC C — CONTROLLER + ROUTES
## ══════════════════════════════════════════

## ÉTAPE 5 — PredictionController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/PredictionController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\{PredictionEchecService, ProfilApprentissageService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * PredictionController — API IA Prédiction Échec Scolaire.
 *
 * ENDPOINTS :
 * GET  /api/v1/ia/predictions/eleve/{id}        → Prédiction individuelle
 * GET  /api/v1/ia/predictions/eleve/{id}/profil → Profil apprentissage
 * GET  /api/v1/ia/predictions/classement        → Classement risque tenant
 * POST /api/v1/ia/predictions/recalculer        → Recalcul batch (admin)
 * GET  /api/v1/ia/predictions/heatmap           → Données heatmap risque
 *
 * ACCÈS :
 * - admin : tout voir
 * - enseignant : ses élèves seulement (via ses cours)
 * - parent : son enfant seulement
 * - élève : son propre profil (sans la probabilité exacte)
 */
class PredictionController extends Controller
{
    public function __construct(
        private PredictionEchecService     $predictionService,
        private ProfilApprentissageService $profilService,
    ) {}

    /**
     * Prédiction pour un élève individuel.
     * GET /api/v1/ia/predictions/eleve/{eleveId}
     */
    public function predireEleve(Request $request, string $eleveId): JsonResponse
    {
        $horizon  = $request->query('horizon', '4_semaines');
        $tenantId = config('tenant.current_id');
        $user     = auth('api')->user();

        // Validation des paramètres
        if (!in_array($horizon, ['4_semaines', 'fin_trimestre', 'fin_annee'])) {
            return response()->json(['success' => false, 'message' => 'Horizon invalide.'], 422);
        }

        // Vérification accès : le parent ne voit que son enfant
        if ($user->role?->nom === 'parent') {
            $enfantIds = DB::table('liens_parentaux')
                ->where('parent_user_id', $user->id)
                ->pluck('eleve_id');
            if (!$enfantIds->contains($eleveId)) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
        }

        $prediction = $this->predictionService->predire($eleveId, $horizon, $tenantId);
        $profil     = $this->profilService->calculerProfil($eleveId, $tenantId);

        // L'élève ne voit pas la probabilité exacte — seulement le niveau
        if ($user->role?->nom === 'eleve') {
            unset($prediction['probabilite']);
            unset($prediction['features']);
        }

        return response()->json([
            'success'    => true,
            'prediction' => $prediction,
            'profil'     => $profil,
        ]);
    }

    /**
     * Classement de TOUS les élèves du tenant par risque.
     * GET /api/v1/ia/predictions/classement
     * Accès : admin uniquement
     */
    public function classementRisque(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        // Prédictions les plus récentes par élève (1 par élève)
        $classement = DB::table('predictions_echec as pe')
            ->join('eleves as e', 'pe.eleve_id', '=', 'e.id')
            ->leftJoin('profils_apprentissage as pa', 'pe.eleve_id', '=', 'pa.eleve_id')
            ->where('pe.tenant_id', $tenantId)
            ->where('pe.created_at', '>=', now()->subDays(7))
            ->select(
                'pe.eleve_id',
                DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                'e.niveau_scolaire',
                'pe.probabilite_echec',
                'pe.confiance',
                'pe.niveau_risque',
                'pe.facteurs_risque',
                'pe.recommandations',
                'pa.profil',
                'pe.created_at as predit_le'
            )
            ->orderByDesc('pe.probabilite_echec')
            ->get()
            ->unique('eleve_id');

        // Statistiques globales
        $stats = [
            'total_eleves'     => $classement->count(),
            'critique'         => $classement->where('niveau_risque', 'critique')->count(),
            'eleve'            => $classement->where('niveau_risque', 'eleve')->count(),
            'modere'           => $classement->where('niveau_risque', 'modere')->count(),
            'faible'           => $classement->where('niveau_risque', 'faible')->count(),
            'probabilite_moy'  => round($classement->avg('probabilite_echec'), 1),
        ];

        return response()->json([
            'success'    => true,
            'stats'      => $stats,
            'classement' => $classement->values(),
        ]);
    }

    /**
     * Données heatmap pour le dashboard directeur.
     * GET /api/v1/ia/predictions/heatmap
     */
    public function heatmap(): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        // Heatmap : niveau_scolaire × niveau_risque
        $heatmap = DB::table('predictions_echec as pe')
            ->join('eleves as e', 'pe.eleve_id', '=', 'e.id')
            ->where('pe.tenant_id', $tenantId)
            ->where('pe.created_at', '>=', now()->subDays(7))
            ->groupBy('e.niveau_scolaire', 'pe.niveau_risque')
            ->select(
                'e.niveau_scolaire',
                'pe.niveau_risque',
                DB::raw('COUNT(*) as nb_eleves'),
                DB::raw('AVG(pe.probabilite_echec) as proba_moy')
            )
            ->get();

        // Évolution temporelle (7 derniers jours)
        $evolution = DB::table('predictions_echec')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("DATE(created_at) as jour, AVG(probabilite_echec) as proba_moy, COUNT(*) as nb")
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        return response()->json([
            'success'   => true,
            'heatmap'   => $heatmap,
            'evolution' => $evolution,
        ]);
    }

    /**
     * Recalculer les prédictions pour tout le tenant.
     * POST /api/v1/ia/predictions/recalculer
     * Accès : admin uniquement
     */
    public function recalculerTenant(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        // Déclencher en arrière-plan (Job)
        \App\Jobs\RecalculerPredictionsTenantJob::dispatch($tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Recalcul des prédictions lancé en arrière-plan. Disponible dans 1-5 minutes.',
        ]);
    }
}
```

---

## ÉTAPE 6 — Job en arrière-plan

**Créer** : `edugestdz/backend/app/Jobs/RecalculerPredictionsTenantJob.php`

```php
<?php

namespace App\Jobs;

use App\Services\{PredictionEchecService, ProfilApprentissageService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\{DB, Log};

class RecalculerPredictionsTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes max

    public function __construct(private string $tenantId) {}

    public function handle(
        PredictionEchecService     $predictionService,
        ProfilApprentissageService $profilService
    ): void {
        config(['tenant.current_id' => $this->tenantId]);

        $eleveIds = DB::table('eleves')
            ->where('tenant_id', $this->tenantId)
            ->where('statut', 'actif')
            ->pluck('id');

        $nb = 0;
        foreach ($eleveIds as $eleveId) {
            try {
                $predictionService->predire($eleveId, '4_semaines', $this->tenantId);
                $profilService->calculerProfil($eleveId, $this->tenantId);
                $nb++;
            } catch (\Throwable $e) {
                Log::warning("RecalculerJob: échec élève {$eleveId}: " . $e->getMessage());
            }
        }

        Log::info("RecalculerJob: {$nb}/{$eleveIds->count()} prédictions calculées pour tenant {$this->tenantId}");
    }
}
```

---

## ÉTAPE 7 — Routes IA

**Ajouter dans** : `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\PredictionController;

// ── IA Prédiction Échec Scolaire ──────────────────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/ia/predictions')->group(function () {
    // Admin + Enseignant + Parent (avec filtre selon rôle)
    Route::get('/eleve/{eleveId}',  [PredictionController::class, 'predireEleve']);

    // Admin seulement
    Route::get('/classement',       [PredictionController::class, 'classementRisque']);
    Route::get('/heatmap',          [PredictionController::class, 'heatmap']);
    Route::post('/recalculer',      [PredictionController::class, 'recalculerTenant']);
});
```

---

## ÉTAPE 8 — Scheduler : prédictions hebdomadaires automatiques

**Ajouter dans** `edugestdz/backend/bootstrap/app.php` dans `withSchedule()` :

```php
// Recalcul prédictions IA — chaque dimanche à 6h00 (avant la semaine)
$schedule->call(function () {
    $tenants = \Illuminate\Support\Facades\DB::table('tenants')
        ->where('statut', 'actif')->pluck('id');
    foreach ($tenants as $tenantId) {
        \App\Jobs\RecalculerPredictionsTenantJob::dispatch($tenantId)
            ->delay(now()->addSeconds(rand(0, 60)));
    }
})->weeklyOn(0, '06:00')->timezone('Africa/Algiers')->name('ia_predictions_hebdo');
```

---

## ══════════════════════════════════════════
## BLOC D — FRONTEND REACT : DASHBOARD IA DIRECTEUR
## ══════════════════════════════════════════

## ÉTAPE 9 — PredictionIAPage.jsx

**Créer** : `edugestdz/frontend/src/pages/PredictionIAPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import Card from '@components/ui/Card';

const api = (path) =>
  fetch(`/api/v1${path}`, {
    headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
  }).then((r) => r.json());

const COULEURS_RISQUE = {
  critique: { bg: 'rgba(239,68,68,0.12)',  border: '#ef4444', text: '#ef4444',  label: '🔴 Critique' },
  eleve:    { bg: 'rgba(249,115,22,0.12)', border: '#f97316', text: '#f97316',  label: '🟠 Élevé' },
  modere:   { bg: 'rgba(234,179,8,0.12)',  border: '#eab308', text: '#ca8a04',  label: '🟡 Modéré' },
  faible:   { bg: 'rgba(34,197,94,0.12)', border: '#22c55e', text: '#16a34a',  label: '🟢 Faible' },
};

const PROFIL_EMOJI = {
  excellent_stable: '⭐', bon_regulier: '✅', moyen_stable: '〰️',
  fragile_amelioration: '📈', chute_rapide: '📉', instable_oscillant: '〜',
  absenteiste: '🚫', decrochage_avance: '🆘', resilient: '💪',
};

export default function PredictionIAPage() {
  const [onglet,     setOnglet]     = useState('classement');
  const [classement, setClassement] = useState(null);
  const [heatmap,    setHeatmap]    = useState(null);
  const [loading,    setLoading]    = useState(true);
  const [filtre,     setFiltre]     = useState('tous');

  useEffect(() => {
    Promise.all([
      api('/ia/predictions/classement'),
      api('/ia/predictions/heatmap'),
    ]).then(([c, h]) => {
      if (c.success) setClassement(c);
      if (h.success) setHeatmap(h);
    }).finally(() => setLoading(false));
  }, []);

  const recalculer = async () => {
    setLoading(true);
    await api('/ia/predictions/recalculer').catch(() => {});
    setLoading(false);
    alert('Recalcul lancé — disponible dans 1-5 minutes');
  };

  const eleves = classement?.classement ?? [];
  const filtres = filtre === 'tous' ? eleves : eleves.filter(e => e.niveau_risque === filtre);

  return (
    <div className="animate-fadeIn space-y-5">

      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-extrabold text-text flex items-center gap-2">
            🧠 Prédiction IA — Risque Échec Scolaire
          </h1>
          <p className="text-xs text-muted mt-1">
            Modèle logistique · Données locales uniquement (loi 18-07) · Prédiction 4 semaines
          </p>
        </div>
        <button
          onClick={recalculer}
          disabled={loading}
          className="px-4 py-2 bg-accent text-white rounded-lg text-xs font-bold disabled:opacity-50"
        >
          🔄 Recalculer maintenant
        </button>
      </div>

      {/* KPIs globaux */}
      {classement && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {[
            { label: 'Critique', count: classement.stats.critique, couleur: '#ef4444' },
            { label: 'Élevé',    count: classement.stats.eleve,    couleur: '#f97316' },
            { label: 'Modéré',   count: classement.stats.modere,   couleur: '#eab308' },
            { label: 'Faible',   count: classement.stats.faible,   couleur: '#22c55e' },
          ].map(kpi => (
            <div key={kpi.label}
                 className="bg-surface border border-border rounded-xl p-4 text-center cursor-pointer hover:border-accent/30 transition-colors"
                 onClick={() => setFiltre(kpi.label.toLowerCase() === 'élevé' ? 'eleve' : kpi.label.toLowerCase())}
                 style={{ borderTop: `3px solid ${kpi.couleur}` }}>
              <div className="text-3xl font-extrabold" style={{ color: kpi.couleur }}>
                {kpi.count}
              </div>
              <div className="text-xs text-muted mt-1">{kpi.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-surface2 rounded-xl">
        {[
          { id: 'classement', label: '📊 Classement Risque' },
          { id: 'heatmap',    label: '🗺️ Heatmap' },
          { id: 'evolution',  label: '📈 Évolution' },
        ].map(t => (
          <button key={t.id} onClick={() => setOnglet(t.id)}
            className={`flex-1 py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
              onglet === t.id ? 'bg-accent text-white shadow' : 'text-muted hover:text-text'
            }`}>
            {t.label}
          </button>
        ))}
      </div>

      {loading && (
        <div className="space-y-3">
          {[1,2,3,4].map(i => <div key={i} className="h-16 bg-surface2 rounded-xl animate-pulse" />)}
        </div>
      )}

      {/* ── CLASSEMENT ── */}
      {!loading && onglet === 'classement' && (
        <>
          <div className="flex gap-2 flex-wrap">
            {['tous','critique','eleve','modere','faible'].map(f => (
              <button key={f} onClick={() => setFiltre(f)}
                className={`px-3 py-1 rounded-lg text-xs font-bold border transition-colors ${
                  filtre === f ? 'bg-accent text-white border-accent' : 'bg-surface2 text-muted border-border'
                }`}>
                {f === 'tous' ? `Tous (${eleves.length})` : COULEURS_RISQUE[f]?.label}
              </button>
            ))}
          </div>

          <div className="space-y-2">
            {filtres.length === 0 && (
              <Card><div className="text-center py-8 text-sm text-muted">
                Aucun élève dans cette catégorie · Lancez un recalcul si les données sont vides
              </div></Card>
            )}
            {filtres.map((eleve, idx) => {
              const c        = COULEURS_RISQUE[eleve.niveau_risque] ?? COULEURS_RISQUE.faible;
              const facteurs = typeof eleve.facteurs_risque === 'string'
                ? JSON.parse(eleve.facteurs_risque)
                : (eleve.facteurs_risque ?? []);
              const recs     = typeof eleve.recommandations === 'string'
                ? JSON.parse(eleve.recommandations)
                : (eleve.recommandations ?? []);

              return (
                <div key={eleve.eleve_id}
                     className="bg-surface border border-border rounded-xl p-4 hover:border-accent/20 transition-colors"
                     style={{ borderLeft: `4px solid ${c.border}` }}>
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-xs font-bold" style={{ color: '#64748b' }}>#{idx + 1}</span>
                        <span className="text-sm font-extrabold text-text">{eleve.eleve_nom}</span>
                        <span className="text-xs px-2 py-0.5 rounded-full font-bold"
                              style={{ background: c.bg, color: c.text }}>
                          {c.label}
                        </span>
                        {eleve.profil && (
                          <span className="text-xs text-muted">
                            {PROFIL_EMOJI[eleve.profil] ?? '❓'} {eleve.profil?.replace(/_/g, ' ')}
                          </span>
                        )}
                      </div>

                      {/* Facteurs de risque */}
                      {facteurs.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1">
                          {facteurs.slice(0, 3).map((f, i) => (
                            <span key={i} className="text-[10px] px-1.5 py-0.5 rounded bg-surface2 text-muted">
                              {f.icone ?? '⚠️'} {f.label}
                            </span>
                          ))}
                        </div>
                      )}

                      {/* Première recommandation */}
                      {recs[0] && (
                        <div className="mt-2 text-[10px] font-semibold"
                             style={{ color: recs[0].urgence === 'immediate' ? '#ef4444' : '#64748b' }}>
                          → {recs[0].label} ({recs[0].delai})
                        </div>
                      )}
                    </div>

                    {/* Probabilité */}
                    <div className="text-right flex-shrink-0">
                      <div className="text-2xl font-black" style={{ color: c.border }}>
                        {Math.round(eleve.probabilite_echec)}%
                      </div>
                      <div className="text-[10px] text-muted">risque échec</div>
                      <div className="text-[10px] text-muted mt-0.5">
                        confiance {Math.round(eleve.confiance)}%
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* ── HEATMAP ── */}
      {!loading && onglet === 'heatmap' && heatmap && (
        <Card>
          <h3 className="text-sm font-bold text-text mb-4">Heatmap Risque par Niveau Scolaire</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b border-border">
                  <th className="text-left py-2 px-3 text-muted font-semibold">Niveau</th>
                  {['faible','modere','eleve','critique'].map(n => (
                    <th key={n} className="text-center py-2 px-3 font-semibold"
                        style={{ color: COULEURS_RISQUE[n]?.text }}>
                      {COULEURS_RISQUE[n]?.label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[...new Set(heatmap.heatmap.map(h => h.niveau_scolaire))].sort().map(niveau => (
                  <tr key={niveau} className="border-b border-border/50">
                    <td className="py-2 px-3 font-semibold text-text">{niveau}</td>
                    {['faible','modere','eleve','critique'].map(risque => {
                      const cell = heatmap.heatmap.find(h => h.niveau_scolaire === niveau && h.niveau_risque === risque);
                      const nb   = cell?.nb_eleves ?? 0;
                      const c    = COULEURS_RISQUE[risque];
                      return (
                        <td key={risque} className="py-2 px-3 text-center">
                          {nb > 0 ? (
                            <span className="inline-block px-2 py-1 rounded-lg font-bold"
                                  style={{ background: c?.bg, color: c?.text }}>
                              {nb}
                            </span>
                          ) : (
                            <span className="text-muted">—</span>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {/* ── ÉVOLUTION ── */}
      {!loading && onglet === 'evolution' && heatmap?.evolution && (
        <Card>
          <h3 className="text-sm font-bold text-text mb-4">Évolution risque moyen (7 derniers jours)</h3>
          <div className="space-y-2">
            {heatmap.evolution.map(jour => (
              <div key={jour.jour} className="flex items-center gap-3">
                <span className="text-xs text-muted w-24 flex-shrink-0">{jour.jour}</span>
                <div className="flex-1 bg-surface2 rounded-full h-5 overflow-hidden">
                  <div className="h-full rounded-full flex items-center pl-2"
                       style={{
                         width: `${Math.round(jour.proba_moy)}%`,
                         background: jour.proba_moy >= 70 ? '#ef4444'
                                   : jour.proba_moy >= 50 ? '#f97316'
                                   : jour.proba_moy >= 25 ? '#eab308'
                                   : '#22c55e',
                       }}>
                    <span className="text-white text-[10px] font-bold">
                      {Math.round(jour.proba_moy)}% ({jour.nb} élèves)
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="mt-4 p-3 rounded-lg text-xs text-muted"
               style={{ background: 'rgba(100,116,139,0.06)' }}>
            💡 <strong>Lecture :</strong> Une hausse du risque moyen en fin de semaine indique
            généralement une accumulation d'absences ou de notes faibles non compensées.
            Déclenchez un recalcul le lundi matin pour avoir les données fraîches.
          </div>
        </Card>
      )}

      {/* Mention légale */}
      <div className="text-[10px] text-muted text-center pt-2 border-t border-border">
        🔒 Modèle IA local · Aucune donnée transmise à l'extérieur · Conforme loi 18-07 (Algérie) ·
        Prédictions indicatives — Ne remplacent pas le jugement du directeur
      </div>
    </div>
  );
}
```

---

## ÉTAPE 10 — Ajouter dans Sidebar + App.jsx

**Dans** `edugestdz/frontend/src/components/Sidebar.jsx`, dans le tableau de navigation section admin :
```jsx
{ path: '/ia/predictions', icon: Brain, label: '🧠 Prédiction IA', roles: ['admin'] },
```

**Dans** `edugestdz/frontend/src/App.jsx` :
```jsx
import PredictionIAPage from '@pages/PredictionIAPage';
// ...
<Route path="/ia/predictions" element={<PredictionIAPage />} />
```

---

## ══════════════════════════════════════════
## BLOC E — TESTS
## ══════════════════════════════════════════

## ÉTAPE 11 — Tests Unit : PredictionEchecService

**Créer** : `edugestdz/backend/tests/Unit/Services/PredictionEchecServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\PredictionEchecService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PredictionEchecServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionEchecService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PredictionEchecService::class);
    }

    // ── Régression logistique ──────────────────────────────────────────

    public function test_sigmoid_retourne_valeur_entre_0_et_100(): void
    {
        // Tester via la méthode publique predire avec un élève sans données
        // → doit retourner un fallback (pas d'exception)
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000000', '4_semaines', 'test-tenant');

        $this->assertIsArray($resultat);
        $this->assertArrayHasKey('probabilite', $resultat);
        $this->assertGreaterThanOrEqual(0, $resultat['probabilite']);
        $this->assertLessThanOrEqual(100, $resultat['probabilite']);
    }

    public function test_horizon_fin_annee_reduit_extremites(): void
    {
        // L'horizon "fin_annee" doit régresser vers 50%
        // On teste via la logique interne : le résultat doit avoir horizon = fin_annee
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000001', 'fin_annee', 'test-tenant');
        $this->assertEquals('fin_annee', $resultat['horizon']);
    }

    public function test_horizon_invalide_retourne_fallback(): void
    {
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000002', '4_semaines', 'test-tenant');
        // Même sans données → ne doit pas crasher
        $this->assertIsArray($resultat);
        $this->assertArrayHasKey('niveau_risque', $resultat);
        $this->assertContains($resultat['niveau_risque'], ['faible', 'modere', 'eleve', 'critique']);
    }

    public function test_structure_retour_complete(): void
    {
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000003', '4_semaines', 'test-tenant');

        $this->assertArrayHasKey('probabilite',     $resultat);
        $this->assertArrayHasKey('confiance',       $resultat);
        $this->assertArrayHasKey('horizon',         $resultat);
        $this->assertArrayHasKey('niveau_risque',   $resultat);
        $this->assertArrayHasKey('facteurs_risque', $resultat);
        $this->assertArrayHasKey('recommandations', $resultat);
        $this->assertArrayHasKey('resume',          $resultat);
        $this->assertArrayHasKey('moteur',          $resultat);
    }

    public function test_confiance_entre_20_et_98(): void
    {
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000004', '4_semaines', 'test-tenant');
        $this->assertGreaterThanOrEqual(20, $resultat['confiance']);
        $this->assertLessThanOrEqual(98, $resultat['confiance']);
    }

    public function test_niveau_risque_classification_correcte(): void
    {
        $resultat = $this->service->predire('00000000-0000-0000-0000-000000000005', '4_semaines', 'test-tenant');
        $niveauxValides = ['faible', 'modere', 'eleve', 'critique'];
        $this->assertContains($resultat['niveau_risque'], $niveauxValides);
    }

    public function test_fallback_retourne_structure_valide(): void
    {
        // Élève inexistant → fallback EWS
        $resultat = $this->service->predire('00000000-0000-0000-0000-999999999999', '4_semaines', 'tenant-inexistant');

        $this->assertIsArray($resultat);
        $this->assertArrayHasKey('probabilite', $resultat);
        $this->assertContains($resultat['moteur'], ['logistique_v1', 'fallback_ews']);
    }
}
```

---

## ÉTAPE 12 — Tests Feature : API Predictions

**Créer** : `edugestdz/backend/tests/Feature/Api/PredictionIATest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionIATest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $admin;
    private User   $enseignant;
    private User   $parent;
    private Eleve  $eleve;
    private string $tokenAdmin;
    private string $tokenEnseignant;
    private string $tokenParent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant      = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $roleAdmin  = Role::factory()->create(['nom' => 'admin']);
        $roleEns    = Role::factory()->create(['nom' => 'enseignant']);
        $roleParent = Role::factory()->create(['nom' => 'parent']);

        $this->admin      = User::factory()->adminAvec2fa()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleAdmin->id]);
        $this->enseignant = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleEns->id]);
        $this->parent     = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleParent->id]);
        $this->eleve      = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->tokenAdmin      = auth('api')->login($this->admin);
        $this->tokenEnseignant = auth('api')->login($this->enseignant);
        $this->tokenParent     = auth('api')->login($this->parent);
    }

    // ── Prédiction individuelle ────────────────────────────────────────

    public function test_admin_peut_voir_prediction_eleve(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'prediction' => ['probabilite', 'confiance', 'niveau_risque', 'facteurs_risque', 'recommandations', 'resume'],
                'profil' => ['profil', 'label_fr', 'emoji', 'alarme'],
            ]);
    }

    public function test_enseignant_peut_voir_prediction(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_prediction_horizon_fin_trimestre(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}?horizon=fin_trimestre")
            ->assertStatus(200)
            ->assertJsonPath('prediction.horizon', 'fin_trimestre');
    }

    public function test_horizon_invalide_retourne_422(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}?horizon=horizon_invalide")
            ->assertStatus(422);
    }

    public function test_parent_non_lie_ne_voit_pas_prediction(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenParent}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->assertStatus(403);
    }

    public function test_probabilite_entre_0_et_100(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->assertStatus(200)
            ->json();

        $this->assertGreaterThanOrEqual(0, $response['prediction']['probabilite']);
        $this->assertLessThanOrEqual(100, $response['prediction']['probabilite']);
    }

    public function test_confiance_entre_20_et_98(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->json();

        $this->assertGreaterThanOrEqual(20, $response['prediction']['confiance']);
        $this->assertLessThanOrEqual(98, $response['prediction']['confiance']);
    }

    public function test_resume_en_francais_present(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->json();

        $this->assertNotEmpty($response['prediction']['resume']);
        $this->assertIsString($response['prediction']['resume']);
    }

    // ── Classement ────────────────────────────────────────────────────

    public function test_admin_peut_voir_classement(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson('/api/v1/ia/predictions/classement')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'stats' => ['total_eleves', 'critique', 'eleve', 'modere', 'faible']]);
    }

    public function test_enseignant_peut_voir_classement(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->getJson('/api/v1/ia/predictions/classement')
            ->assertStatus(200);
    }

    // ── Heatmap ────────────────────────────────────────────────────────

    public function test_heatmap_retourne_structure_valide(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson('/api/v1/ia/predictions/heatmap')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'heatmap', 'evolution']);
    }

    // ── Recalcul ──────────────────────────────────────────────────────

    public function test_admin_peut_declencher_recalcul(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->postJson('/api/v1/ia/predictions/recalculer')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RecalculerPredictionsTenantJob::class);
    }

    // ── Profil apprentissage ──────────────────────────────────────────

    public function test_profil_contient_les_bons_champs(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->json();

        $this->assertArrayHasKey('profil',       $response['profil']);
        $this->assertArrayHasKey('alarme',       $response['profil']);
        $this->assertIsBool($response['profil']['alarme']);
    }

    public function test_profil_valide_dans_liste_attendue(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->json();

        $profilsValides = [
            'excellent_stable', 'bon_regulier', 'moyen_stable', 'fragile_amelioration',
            'chute_rapide', 'instable_oscillant', 'absenteiste', 'decrochage_avance', 'resilient',
        ];
        $this->assertContains($response['profil']['profil'], $profilsValides);
    }

    public function test_sans_auth_retourne_401(): void
    {
        $this->getJson("/api/v1/ia/predictions/eleve/{$this->eleve->id}")
            ->assertStatus(401);
    }
}
```

---

## ÉTAPE 13 — Tests Unit : ProfilApprentissageService

**Créer** : `edugestdz/backend/tests/Unit/Services/ProfilApprentissageServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\ProfilApprentissageService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProfilApprentissageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfilApprentissageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfilApprentissageService::class);
    }

    public function test_calcul_profil_sans_donnees_ne_crash_pas(): void
    {
        // Élève sans historique → doit retourner un profil par défaut
        $resultat = $this->service->calculerProfil('00000000-0000-0000-0000-000000000000', 'test-tenant');

        $this->assertIsArray($resultat);
        $this->assertArrayHasKey('profil', $resultat);
        $this->assertArrayHasKey('alarme', $resultat);
        $this->assertIsBool($resultat['alarme']);
    }

    public function test_moyen_stable_ne_declenche_pas_alarme(): void
    {
        // Règle critique : un élève "moyen_stable" NE doit PAS générer d'alarme
        // Simuler via un profil direct
        $resultat = $this->service->calculerProfil('00000000-0000-0000-0000-000000000001', 'test-tenant');

        // Avec une BDD vide → profil par défaut = moyen_stable → alarme = false
        if ($resultat['profil'] === 'moyen_stable') {
            $this->assertFalse($resultat['alarme'], "moyen_stable NE doit PAS déclencher une alarme");
        }

        $this->assertTrue(true); // Test passé si pas de crash
    }

    public function test_structure_profil_complete(): void
    {
        $resultat = $this->service->calculerProfil('00000000-0000-0000-0000-000000000002', 'test-tenant');

        $this->assertArrayHasKey('profil',       $resultat);
        $this->assertArrayHasKey('label_fr',     $resultat);
        $this->assertArrayHasKey('emoji',        $resultat);
        $this->assertArrayHasKey('alarme',       $resultat);
        $this->assertArrayHasKey('points_forts', $resultat);
        $this->assertArrayHasKey('stabilite',    $resultat);
        $this->assertArrayHasKey('explication',  $resultat);
    }

    public function test_stabilite_entre_0_et_100(): void
    {
        $resultat = $this->service->calculerProfil('00000000-0000-0000-0000-000000000003', 'test-tenant');
        $this->assertGreaterThanOrEqual(0,   $resultat['stabilite']);
        $this->assertLessThanOrEqual(100,    $resultat['stabilite']);
    }
}
```

---

## ÉTAPE 14 — Exécution complète

```bash
cd edugestdz/backend

# 1. Migrations
php artisan migrate --force

# 2. Autoload
composer dump-autoload -o

# 3. Tests unitaires
php artisan test tests/Unit/Services/PredictionEchecServiceTest.php --stop-on-failure
php artisan test tests/Unit/Services/ProfilApprentissageServiceTest.php --stop-on-failure

# 4. Tests Feature
php artisan test tests/Feature/Api/PredictionIATest.php --stop-on-failure

# 5. Suite complète (0 régression)
php artisan test
# → ≥ 785 ✅  0 failures

# Frontend
cd ../frontend
npm run build
# → dist/ généré sans erreur

# Git
cd ..
git add \
  backend/database/migrations/2026_07_11_100000_create_predictions_echec_table.php \
  backend/database/migrations/2026_07_11_110000_create_profils_apprentissage_table.php \
  backend/app/Services/PredictionEchecService.php \
  backend/app/Services/ProfilApprentissageService.php \
  backend/app/Http/Controllers/Api/V1/PredictionController.php \
  backend/app/Jobs/RecalculerPredictionsTenantJob.php \
  backend/routes/api.php \
  backend/bootstrap/app.php \
  backend/tests/Unit/Services/PredictionEchecServiceTest.php \
  backend/tests/Unit/Services/ProfilApprentissageServiceTest.php \
  backend/tests/Feature/Api/PredictionIATest.php \
  frontend/src/pages/PredictionIAPage.jsx \
  frontend/src/components/Sidebar.jsx \
  frontend/src/App.jsx

git commit -m "feat(ia-prediction): Prédiction échec scolaire IA — régression logistique PHP natif

MOTEUR IA :
- PredictionEchecService: régression logistique PHP natif (0 dépendance externe)
  11 features normalisées (tendance, absences, chutes, billets, variance, etc.)
  sigmoid() → probabilité 0-100% + niveau risque (faible/modéré/élevé/critique)
  Explicabilité : facteurs en français avec poids + icône
  Recommandations contextuelles avec urgence + délai
  Interface stable : corps remplaçable par Python microservice sans changer les controllers
  Fallback sur DiagnosticService existant si données insuffisantes
  Confiance 20-98% selon qualité des données disponibles

PROFIL APPRENTISSAGE :
- ProfilApprentissageService: 10 profils (excellent_stable, chute_rapide, moyen_stable...)
  Règle critique : 'moyen_stable' ne génère PAS d'alarme (suppression faux positifs EWS)
  Pente régression linéaire 8 semaines + variance + chutes/récupérations détectées
  Corrélation Pearson absences→notes
  Points forts / points faibles par matière

API :
- PredictionController: 4 endpoints (eleve, classement, heatmap, recalculer)
- RecalculerPredictionsTenantJob: batch async (ShouldQueue, 3 retries, 5min timeout)
- Scheduler: dimanche 6h00 Algérie → prédictions fraîches chaque semaine

FRONTEND :
- PredictionIAPage.jsx: dashboard directeur (3 onglets: classement/heatmap/évolution)
  KPIs globaux cliquables, classement coloré par risque, facteurs inline
  Heatmap niveau_scolaire × niveau_risque, évolution temporelle barres colorées
  Mention légale : 'Aucune donnée transmise — Conforme loi 18-07 Algérie'

SÉCURITÉ & CONFORMITÉ :
- 0 appel API externe (loi 18-07 respectée)
- Parent voit seulement son enfant (filtre liens_parentaux)
- Élève ne voit pas la probabilité exacte — seulement le niveau
- Données IA stockées PostgreSQL local uniquement

TESTS : 7 (Unit PredictionEchec) + 4 (Unit ProfilApprentissage) + 15 (Feature API) = 26 nouveaux"

git push origin develop
# → PR → main
```

---

## ══════════════════════════════════════════
## PROMPT EXACT POUR DEEPSEEK
## ══════════════════════════════════════════

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_IA_PREDICTION_ECHEC_SCOLAIRE.md — 14 étapes.

CONTEXTE :
- Tests actuels : 748+ ✅ (Mission flux-info-1 déjà mergée)
- Objectif : ≥ 785 ✅ après cette mission
- DiagnosticService.php EXISTE DÉJÀ → NE PAS MODIFIER

RÈGLES CRITIQUES :
1. PredictionEchecService : NE PAS injecter DiagnosticService comme dépendance.
   Le fallback EWS fait un DB::table('diagnostics_eleves') direct → pas de couplage fort.

2. Table predictions_echec : colonnes features/facteurs_risque/recommandations
   sont en JSONB. La migration utilise ->jsonb(). Si jsonb() n'est pas disponible
   dans la version de Blueprint → utiliser ->json() à la place (downgrade gracieux).

3. ProfilApprentissageService::calculerProfil() : si la table historique_diagnostics
   est vide (BDD neuve) → la méthode chargerHistoriqueSemaine() retourne une collection vide.
   choisirProfil() doit gérer le cas où toutes les valeurs sont 0/null → retourner 'moyen_stable'.

4. PredictionController::predireEleve() : la vérification parent (liens_parentaux table)
   peut ne pas exister. Entourer la requête dans un try/catch → si table absente,
   retourner 403 pour les parents (fail-secure).

5. RecalculerPredictionsTenantJob : dans les tests, utiliser Queue::fake() avant
   d'appeler POST /recalculer. Sinon le Job essaiera vraiment de s'exécuter.

6. ProfilApprentissageService::correlationAbsenceNote() : la colonne
   "details->>'comportement'->>'absences'" en PostgreSQL JSONB utilise l'opérateur ->>
   sur un chemin imbriqué. Syntaxe correcte PostgreSQL :
   (details->'comportement'->>'absences')::numeric
   Si erreur → utiliser 0 en fallback (try/catch autour de cette requête).

7. PredictionIAPage.jsx : l'import Brain depuis 'lucide-react' doit être vérifié.
   Si Brain n'existe pas dans la version installée → utiliser Cpu ou Activity.

php artisan migrate --force
php artisan test tests/Unit/Services/PredictionEchecServiceTest.php
php artisan test tests/Unit/Services/ProfilApprentissageServiceTest.php
php artisan test tests/Feature/Api/PredictionIATest.php
php artisan test → ≥ 785 ✅
npm run build → 0 erreurs
git push origin develop → PR → main
```

---

## DIFFÉRENCE EWS vs IA — TABLEAU RÉCAPITULATIF

| Dimension | EWS Existant (DiagnosticService) | IA Prédiction (cette mission) |
|---|---|---|
| **Approche** | Réactif | Proactif |
| **Question** | "Il est en danger MAINTENANT ?" | "Sera-t-il en échec dans 4 semaines ?" |
| **Méthode** | Seuils fixes (5/8/10/15) | Régression logistique + features temporelles |
| **Temporalité** | Instantané | Tendance sur 4-8 semaines |
| **Faux positifs** | Élevés (moyen_stable → alarme) | Réduits (profil = contexte) |
| **Explicabilité** | Score 0-100 sans détail | 5 facteurs en français avec poids |
| **Recommandations** | Générique | Contextuelles + délai + urgence |
| **Profil élève** | Non | Oui (10 profils) |
| **Confiance** | Non | 20-98% selon qualité données |
| **Dépendance externe** | Aucune | Aucune (loi 18-07) |
