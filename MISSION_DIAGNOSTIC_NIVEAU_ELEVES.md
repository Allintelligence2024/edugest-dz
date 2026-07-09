# 🤖 MISSION DEEPSEEK — Module Diagnostic de Niveau des Élèves (Early Warning System)
## EduGest DZ · Branche : develop · 4 Juillet 2026
## Tests actuels : 444+ ✅ · Objectif : ≥ 460 ✅ · 0 régression

---

## CONTEXTE — Recherche et fondements pédagogiques

### Ce que font les meilleurs systèmes mondiaux
D'après la recherche (PRONOTE France, EWS Pro USA, RADAR AI, Iowa MTSS) :

**Les 3 indicateurs universels de risque scolaire :**
```
1. NOTES       → moyenne en chute, note < seuil, tendance négative
2. ABSENCES    → corrélation forte avec le décrochage (>3 absences/mois = risque)
3. COMPORTEMENT → billets retard, convocations, incidents répétés
```

**Système de niveaux (tiers) utilisé internationalement :**
```
TIER 1 — Universel    : tous les élèves, suivi normal
TIER 2 — Ciblé        : élèves à risque modéré → rattrapage, soutien
TIER 3 — Intensif     : élèves en grande difficulté → convocation parents obligatoire
```

**Seuils déclencheurs (adaptés au contexte algérien) :**
```
Moyenne < 5/20          → CRITIQUE (Tier 3) — convocation parents
Moyenne 5-8/20          → DANGER   (Tier 2) — rattrapage obligatoire
Moyenne 8-10/20         → VIGILANCE (Tier 2) — suivi renforcé
Moyenne ≥ 10/20         → NORMAL   (Tier 1)
Moyenne ≥ 15/20         → EXCELLENT → détection des meilleurs élèves

Tendance chute ≥ 3pts   → ALERTE DÉCROISSANCE même si la moyenne est correcte
3 notes < 5 consécutives → ALERTE SÉRIE CRITIQUE
Absences > 3/mois       → ALERTE ABSENTÉISME (amplifie le risque)
Billets retard > 5/mois → ALERTE COMPORTEMENT
```

### Ce qu'on construit pour EduGest DZ

Un **Early Warning System (EWS) adapté au contexte algérien** qui :
1. **Surveille en continu** chaque note saisie par l'enseignant
2. **Calcule automatiquement** un score de risque (0-100) pour chaque élève
3. **Classe** les élèves en 5 niveaux : Excellent / Normal / Vigilance / Danger / Critique
4. **Déclenche des actions** : rattrapage programmé, SMS parent, convocation
5. **Détecte les meilleurs** élèves pour les valoriser (mention, certificat)
6. **Génère un rapport** hebdomadaire envoyé au directeur

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Migration : tables diagnostic

**Créer :**
`edugestdz/backend/database/migrations/2026_07_04_200000_create_diagnostic_niveau_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Diagnostic par élève (mis à jour à chaque nouvelle note) ─────
        Schema::create('diagnostics_eleves', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id')->unique(); // 1 diagnostic par élève
            $table->string('niveau_global');
            // Valeurs : excellent | normal | vigilance | danger | critique
            $table->decimal('score_risque', 5, 2)->default(0);
            // Score 0-100 : 0=excellent, 100=critique maximum

            // Indicateurs académiques
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->decimal('moyenne_trimestre_precedent', 5, 2)->nullable();
            $table->decimal('tendance', 5, 2)->nullable();
            // Positif = progression, négatif = régression (en points)

            $table->integer('nb_notes_sous_5')->default(0);
            $table->integer('nb_notes_sous_10')->default(0);
            $table->integer('nb_notes_consecutives_sous_5')->default(0);
            // Séries de notes critiques consécutives

            // Matières en difficulté
            $table->jsonb('matieres_en_danger')->default('[]');
            // [{"matiere":"Maths","moyenne":4.5,"niveau":"critique"}]
            $table->jsonb('matieres_excellentes')->default('[]');
            // [{"matiere":"Arabe","moyenne":17.5,"niveau":"excellent"}]

            // Indicateurs comportementaux
            $table->integer('nb_absences_mois')->default(0);
            $table->integer('nb_retards_mois')->default(0);
            $table->integer('nb_billets_mois')->default(0);

            // Actions déclenchées
            $table->boolean('rattrapage_requis')->default(false);
            $table->boolean('convocation_requise')->default(false);
            $table->boolean('sms_alerte_envoye')->default(false);
            $table->boolean('mention_excellence')->default(false);

            $table->timestamp('derniere_analyse')->nullable();
            $table->timestamp('prochaine_analyse')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'niveau_global'],  'idx_diag_tenant_niveau');
            $table->index(['tenant_id', 'score_risque'],   'idx_diag_tenant_score');
            $table->index(['rattrapage_requis'],            'idx_diag_rattrapage');
            $table->index(['convocation_requise'],          'idx_diag_convocation');
        });

        // ── Historique des analyses (pour suivre la progression) ─────────
        Schema::create('historique_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->string('niveau_global');
            $table->decimal('score_risque', 5, 2);
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->decimal('tendance', 5, 2)->nullable();
            $table->jsonb('details')->default('{}');
            $table->timestamp('analyse_le');

            $table->index(['eleve_id', 'analyse_le'], 'idx_histo_eleve_date');
            $table->index(['tenant_id', 'analyse_le'], 'idx_histo_tenant_date');
        });

        // ── Actions de rattrapage planifiées ─────────────────────────────
        Schema::create('plans_rattrapage', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->uuid('enseignant_id')->nullable();
            $table->string('matiere');
            $table->text('objectifs');    // Ce qu'on vise corriger
            $table->text('programme');   // Description du rattrapage
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['planifié', 'en_cours', 'terminé', 'annulé'])
                ->default('planifié');
            $table->text('resultat')->nullable(); // Résultat après le rattrapage
            $table->uuid('cree_par');
            $table->timestamps();

            $table->index(['eleve_id', 'statut'], 'idx_rattrapage_eleve_statut');
        });

        // ── Convocations parents ──────────────────────────────────────────
        Schema::create('convocations_parents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->string('motif');
            // Valeurs : niveau_critique | absences_excessives | comportement | autre
            $table->text('message'); // Message personnalisé envoyé aux parents
            $table->enum('canal', ['sms', 'whatsapp', 'email', 'courrier'])->default('sms');
            $table->enum('statut', ['envoyée', 'confirmée', 'réalisée', 'annulée'])
                ->default('envoyée');
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamp('rendez_vous_le')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->uuid('cree_par');
            $table->timestamps();

            $table->index(['tenant_id', 'statut'],   'idx_conv_tenant_statut');
            $table->index(['eleve_id', 'statut'],    'idx_conv_eleve_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocations_parents');
        Schema::dropIfExists('plans_rattrapage');
        Schema::dropIfExists('historique_diagnostics');
        Schema::dropIfExists('diagnostics_eleves');
    }
};
```

---

## ÉTAPE 2 — Models

**Créer :** `edugestdz/backend/app/Models/DiagnosticEleve.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DiagnosticEleve extends Model
{
    use HasUuids;

    protected $table = 'diagnostics_eleves';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'niveau_global', 'score_risque',
        'moyenne_generale', 'moyenne_trimestre_precedent', 'tendance',
        'nb_notes_sous_5', 'nb_notes_sous_10', 'nb_notes_consecutives_sous_5',
        'matieres_en_danger', 'matieres_excellentes',
        'nb_absences_mois', 'nb_retards_mois', 'nb_billets_mois',
        'rattrapage_requis', 'convocation_requise', 'sms_alerte_envoye', 'mention_excellence',
        'derniere_analyse', 'prochaine_analyse',
    ];

    protected $casts = [
        'matieres_en_danger'   => 'array',
        'matieres_excellentes' => 'array',
        'rattrapage_requis'    => 'boolean',
        'convocation_requise'  => 'boolean',
        'sms_alerte_envoye'    => 'boolean',
        'mention_excellence'   => 'boolean',
        'score_risque'         => 'decimal:2',
        'moyenne_generale'     => 'decimal:2',
        'tendance'             => 'decimal:2',
        'derniere_analyse'     => 'datetime',
        'prochaine_analyse'    => 'datetime',
    ];

    // Couleurs et labels par niveau
    public const NIVEAUX = [
        'excellent'  => ['label' => '⭐ Excellent',  'color' => '#4ade80', 'score_max' => 10],
        'normal'     => ['label' => '✅ Normal',      'color' => '#60a5fa', 'score_max' => 30],
        'vigilance'  => ['label' => '⚠️ Vigilance',  'color' => '#fb923c', 'score_max' => 55],
        'danger'     => ['label' => '🔴 Danger',      'color' => '#f87171', 'score_max' => 75],
        'critique'   => ['label' => '🚨 Critique',   'color' => '#ef4444', 'score_max' => 100],
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function historique()
    {
        return $this->hasMany(HistoriqueDiagnostic::class, 'eleve_id', 'eleve_id')
            ->orderByDesc('analyse_le');
    }

    public function plans()
    {
        return $this->hasMany(PlanRattrapage::class, 'eleve_id', 'eleve_id');
    }

    public function convocations()
    {
        return $this->hasMany(ConvocationParent::class, 'eleve_id', 'eleve_id');
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->where('niveau_global', $niveau);
    }

    public function scopeRequiertAction($query)
    {
        return $query->where(function ($q) {
            $q->where('rattrapage_requis', true)
              ->orWhere('convocation_requise', true);
        });
    }

    public function getNiveauInfoAttribute(): array
    {
        return self::NIVEAUX[$this->niveau_global] ?? self::NIVEAUX['normal'];
    }
}
```

**Créer :** `edugestdz/backend/app/Models/PlanRattrapage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlanRattrapage extends Model
{
    use HasUuids;

    protected $table = 'plans_rattrapage';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'enseignant_id', 'matiere',
        'objectifs', 'programme', 'date_debut', 'date_fin',
        'statut', 'resultat', 'cree_par',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function eleve()      { return $this->belongsTo(Eleve::class); }
    public function enseignant() { return $this->belongsTo(User::class, 'enseignant_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/ConvocationParent.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ConvocationParent extends Model
{
    use HasUuids;

    protected $table = 'convocations_parents';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'motif', 'message', 'canal',
        'statut', 'envoyee_le', 'rendez_vous_le', 'compte_rendu', 'cree_par',
    ];

    protected $casts = [
        'envoyee_le'     => 'datetime',
        'rendez_vous_le' => 'datetime',
    ];

    public function eleve() { return $this->belongsTo(Eleve::class); }
}
```

**Créer :** `edugestdz/backend/app/Models/HistoriqueDiagnostic.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class HistoriqueDiagnostic extends Model
{
    use HasUuids;

    protected $table = 'historique_diagnostics';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'eleve_id', 'niveau_global', 'score_risque',
        'moyenne_generale', 'tendance', 'details', 'analyse_le',
    ];

    protected $casts = [
        'details'     => 'array',
        'analyse_le'  => 'datetime',
        'score_risque'=> 'decimal:2',
    ];
}
```

---

## ÉTAPE 3 — DiagnosticService (cœur du système)

**Créer :** `edugestdz/backend/app/Services/DiagnosticService.php`

```php
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
    // ── Seuils configurables ─────────────────────────────────────────────────
    private const SEUIL_CRITIQUE        = 5.0;   // < 5/20  → critique
    private const SEUIL_DANGER          = 8.0;   // < 8/20  → danger
    private const SEUIL_VIGILANCE       = 10.0;  // < 10/20 → vigilance
    private const SEUIL_EXCELLENCE      = 15.0;  // ≥ 15/20 → excellent
    private const SEUIL_ABSENCES_ALERTE = 3;     // > 3 absences/mois → alerte
    private const SEUIL_RETARDS_ALERTE  = 5;     // > 5 retards/mois → alerte
    private const SEUIL_CHUTE_ALERTE    = 3.0;   // chute ≥ 3pts/trimestre → alerte
    private const SERIE_CRITIQUE        = 3;     // N notes consécutives < 5 → critique

    /**
     * Analyser UN élève et mettre à jour son diagnostic.
     * Appelé automatiquement à chaque nouvelle note (Observer).
     */
    public function analyserEleve(string $eleveId): DiagnosticEleve
    {
        $eleve = Eleve::findOrFail($eleveId);

        // ── 1. Collecter les données ──────────────────────────────────────
        $notes          = $this->getNotesTrimestre($eleveId);
        $comportement   = $this->getComportementMois($eleveId);
        $notesPrecedent = $this->getNotesTrimestre($eleveId, -1); // trimestre précédent

        // ── 2. Calculer les indicateurs ───────────────────────────────────
        $moyenneActuelle  = $notes->avg('note') ?? null;
        $moyennePrecedent = $notesPrecedent->avg('note') ?? null;
        $tendance         = ($moyenneActuelle && $moyennePrecedent)
            ? round($moyenneActuelle - $moyennePrecedent, 2)
            : null;

        // Notes critiques
        $nbSous5     = $notes->where('note', '<', 5)->count();
        $nbSous10    = $notes->where('note', '<', 10)->count();
        $serieCritique = $this->calculerSerieCritique($notes);

        // Matières en difficulté et excellentes
        $parMatiere       = $notes->groupBy('evaluation.matiere.nom_fr')
            ->map(fn($n) => round($n->avg('note'), 2));

        $matieresDanger    = $parMatiere->filter(fn($m) => $m < self::SEUIL_DANGER)
            ->map(fn($moy, $mat) => [
                'matiere' => $mat,
                'moyenne' => $moy,
                'niveau'  => $moy < self::SEUIL_CRITIQUE ? 'critique' : 'danger',
            ])->values()->toArray();

        $matieresExcellentes = $parMatiere->filter(fn($m) => $m >= self::SEUIL_EXCELLENCE)
            ->map(fn($moy, $mat) => ['matiere' => $mat, 'moyenne' => $moy])
            ->values()->toArray();

        // ── 3. Calculer le score de risque (0-100) ────────────────────────
        $scoreRisque = $this->calculerScoreRisque(
            $moyenneActuelle,
            $tendance,
            $serieCritique,
            $comportement,
            $nbSous5
        );

        // ── 4. Déterminer le niveau global ────────────────────────────────
        $niveauGlobal = $this->determinerNiveau($scoreRisque, $moyenneActuelle);

        // ── 5. Déterminer les actions requises ────────────────────────────
        $rattrapageRequis  = $niveauGlobal === 'danger' || $niveauGlobal === 'critique';
        $convocationRequise = $niveauGlobal === 'critique'
            || ($niveauGlobal === 'danger' && $serieCritique >= self::SERIE_CRITIQUE)
            || $comportement['absences'] > self::SEUIL_ABSENCES_ALERTE * 2;
        $mentionExcellence = $niveauGlobal === 'excellent'
            && ($moyenneActuelle >= 17) && ($comportement['absences'] === 0);

        // ── 6. Sauvegarder le diagnostic ──────────────────────────────────
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

        // ── 7. Sauvegarder dans l'historique ─────────────────────────────
        HistoriqueDiagnostic::create([
            'tenant_id'       => $eleve->tenant_id,
            'eleve_id'        => $eleveId,
            'niveau_global'   => $niveauGlobal,
            'score_risque'    => $scoreRisque,
            'moyenne_generale'=> $moyenneActuelle,
            'tendance'        => $tendance,
            'details'         => [
                'matieres_danger'     => $matieresDanger,
                'serie_critique'      => $serieCritique,
                'comportement'        => $comportement,
            ],
            'analyse_le'      => now(),
        ]);

        Log::info("Diagnostic élève {$eleveId}: {$niveauGlobal} (score: {$scoreRisque})");

        return $diagnostic;
    }

    /**
     * Analyser TOUS les élèves d'un tenant.
     * Appelé par le scheduler hebdomadaire.
     */
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

    /**
     * Calculer le score de risque (0 = excellent, 100 = critique maximum).
     */
    private function calculerScoreRisque(
        ?float $moyenne,
        ?float $tendance,
        int $serieCritique,
        array $comportement,
        int $nbSous5
    ): float {
        $score = 0;

        // Composante 1 : Moyenne générale (poids 50%)
        if ($moyenne !== null) {
            if ($moyenne < self::SEUIL_CRITIQUE)   $score += 50;
            elseif ($moyenne < self::SEUIL_DANGER) $score += 35;
            elseif ($moyenne < self::SEUIL_VIGILANCE) $score += 20;
            elseif ($moyenne >= self::SEUIL_EXCELLENCE) $score += 0;
            else $score += 10;
        }

        // Composante 2 : Tendance (poids 20%)
        if ($tendance !== null) {
            if ($tendance <= -self::SEUIL_CHUTE_ALERTE) $score += 20; // forte chute
            elseif ($tendance < 0)                      $score += 10; // légère chute
            elseif ($tendance > 2)                      $score -= 5;  // progression = bonus
        }

        // Composante 3 : Série de notes critiques (poids 15%)
        $score += min($serieCritique * 5, 15);

        // Composante 4 : Comportement (poids 15%)
        if ($comportement['absences'] > self::SEUIL_ABSENCES_ALERTE)
            $score += min($comportement['absences'] * 2, 10);
        if ($comportement['retards'] > self::SEUIL_RETARDS_ALERTE)
            $score += 5;

        return max(0, min(100, round($score, 2)));
    }

    /**
     * Déterminer le niveau global selon le score et la moyenne.
     */
    private function determinerNiveau(float $score, ?float $moyenne): string
    {
        // Priorité à la moyenne absolue pour les cas extrêmes
        if ($moyenne !== null && $moyenne < self::SEUIL_CRITIQUE) return 'critique';
        if ($moyenne !== null && $moyenne >= self::SEUIL_EXCELLENCE && $score <= 10) return 'excellent';

        // Sinon selon le score de risque
        if ($score >= 76) return 'critique';
        if ($score >= 56) return 'danger';
        if ($score >= 31) return 'vigilance';
        if ($score <= 10 && ($moyenne ?? 20) >= self::SEUIL_EXCELLENCE) return 'excellent';
        return 'normal';
    }

    /**
     * Calculer la série de notes consécutives < 5.
     */
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

    /**
     * Récupérer les notes du trimestre courant (ou précédent si offset=-1).
     */
    private function getNotesTrimestre(string $eleveId, int $offset = 0)
    {
        $debut = now()->startOfQuarter()->addMonths($offset * 3);
        $fin   = $debut->copy()->endOfQuarter();

        return Note::where('eleve_id', $eleveId)
            ->whereNotNull('note')
            ->whereBetween('created_at', [$debut, $fin])
            ->with('evaluation.matiere:id,nom_fr')
            ->get();
    }

    /**
     * Récupérer les indicateurs comportementaux du mois.
     */
    private function getComportementMois(string $eleveId): array
    {
        $debut = now()->startOfMonth();
        $fin   = now()->endOfMonth();

        $absences = AbsenceJournaliere::where('eleve_id', $eleveId)
            ->whereBetween('date_absence', [$debut->format('Y-m-d'), $fin->format('Y-m-d')])
            ->count();

        $billets = \App\Models\Billet::where('eleve_id', $eleveId)
            ->whereBetween('created_at', [$debut, $fin])
            ->get();

        return [
            'absences' => $absences,
            'retards'  => $billets->where('type', 'retard')->count(),
            'billets'  => $billets->count(),
        ];
    }

    /**
     * Obtenir le résumé du diagnostic pour le dashboard.
     */
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
```

---

## ÉTAPE 4 — Observer : déclencher le diagnostic à chaque note saisie

**Créer :** `edugestdz/backend/app/Observers/NoteObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\DiagnosticService;
use App\Services\FirebaseService;
use App\Services\SmsService;
use App\Models\ConvocationParent;
use Illuminate\Support\Facades\Log;

class NoteObserver
{
    public function __construct(
        private DiagnosticService $diagnostic,
        private FirebaseService   $firebase,
        private SmsService        $sms,
    ) {}

    public function created(Note $note): void
    {
        if (!$note->note || !$note->eleve_id) return;

        // Analyser l'élève en arrière-plan (dispatch job si queue dispo)
        try {
            $diag = $this->diagnostic->analyserEleve($note->eleve_id);
            $this->notifierSiNecessaire($diag, $note);
        } catch (\Throwable $e) {
            Log::warning("DiagnosticObserver Note: " . $e->getMessage());
        }
    }

    public function updated(Note $note): void
    {
        if ($note->isDirty('note') && $note->eleve_id) {
            $this->created($note); // même logique
        }
    }

    private function notifierSiNecessaire($diag, Note $note): void
    {
        $eleve = $note->eleve;
        if (!$eleve) return;

        // ── Alerte critique → SMS parent immédiat ─────────────────────────
        if ($diag->niveau_global === 'critique' && !$diag->sms_alerte_envoye) {
            $msg = "🚨 EduGest : {$eleve->prenom} {$eleve->nom} est en SITUATION CRITIQUE "
                 . "(moyenne: {$diag->moyenne_generale}/20). "
                 . "Une convocation vous sera envoyée prochainement.";

            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try { $this->sms->send($tel, $msg); } catch (\Throwable) {}
                }
            }

            $this->firebase->notifyParentsEleve(
                $eleve->id,
                '🚨 Situation critique',
                "Niveau académique de {$eleve->prenom} nécessite une attention urgente.",
                ['type' => 'diagnostic', 'niveau' => 'critique', 'eleve_id' => $eleve->id]
            );

            $diag->update(['sms_alerte_envoye' => true]);
        }

        // ── Danger → notification push seulement ─────────────────────────
        if ($diag->niveau_global === 'danger') {
            $this->firebase->notifyParentsEleve(
                $eleve->id,
                '⚠️ Niveau en baisse',
                "{$eleve->prenom} a des difficultés (moyenne: {$diag->moyenne_generale}/20). Contactez l'établissement.",
                ['type' => 'diagnostic', 'niveau' => 'danger', 'eleve_id' => $eleve->id]
            );
        }

        // ── Excellence → félicitations push ──────────────────────────────
        if ($diag->mention_excellence) {
            $this->firebase->notifyParentsEleve(
                $eleve->id,
                '⭐ Félicitations !',
                "{$eleve->prenom} est parmi les meilleurs élèves (moyenne: {$diag->moyenne_generale}/20) !",
                ['type' => 'diagnostic', 'niveau' => 'excellent', 'eleve_id' => $eleve->id]
            );
        }
    }
}
```

---

## ÉTAPE 5 — Commande scheduler : analyse hebdomadaire

**Créer :** `edugestdz/backend/app/Console/Commands/DiagnosticHebdomadaireCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\DiagnosticService;
use App\Services\SmsService;
use App\Models\DiagnosticEleve;
use App\Models\ConvocationParent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnosticHebdomadaireCommand extends Command
{
    protected $signature   = 'edugest:diagnostic-hebdomadaire {--tenant= : Analyser un tenant spécifique}';
    protected $description = 'Analyse hebdomadaire du niveau de tous les élèves + envoi convocations';

    public function handle(DiagnosticService $diagnostic, SmsService $sms): int
    {
        $tenantId = $this->option('tenant');
        $this->info('🔍 Analyse diagnostique hebdomadaire...');

        // 1. Analyser tous les élèves
        $resultats = $diagnostic->analyserTousLesEleves($tenantId);
        $this->info("✅ Analyses : {$resultats['total']} élèves");
        $this->info("🚨 Critiques : {$resultats['critiques']}");
        $this->info("🔴 Dangers   : {$resultats['dangers']}");
        $this->info("⭐ Excellents: {$resultats['excellents']}");

        // 2. Générer les convocations pour les cas critiques non encore convoqués
        $aConvoquer = DiagnosticEleve::where('convocation_requise', true)
            ->whereDoesntHave('convocations', fn($q) =>
                $q->where('statut', '!=', 'annulée')
                  ->where('created_at', '>=', now()->subWeeks(4))
            )
            ->with('eleve.parents')
            ->get();

        $convoqués = 0;
        foreach ($aConvoquer as $diag) {
            $eleve = $diag->eleve;
            if (!$eleve) continue;

            $motif = $diag->niveau_global === 'critique'
                ? 'niveau_critique'
                : ($diag->nb_absences_mois > 6 ? 'absences_excessives' : 'niveau_critique');

            $msg = "EduGest — Convocation Parent\n\n"
                 . "Établissement : " . config('app.name') . "\n"
                 . "Élève : {$eleve->prenom} {$eleve->nom} ({$eleve->niveau_scolaire})\n"
                 . "Motif : " . ($motif === 'niveau_critique'
                     ? "Niveau académique insuffisant (moyenne: {$diag->moyenne_generale}/20)"
                     : "Absentéisme excessif ({$diag->nb_absences_mois} absences ce mois)") . "\n"
                 . "Action requise : Prendre rendez-vous avec le directeur.\n"
                 . "Contact : " . config('app.contact_phone', 'Voir l\'établissement');

            // Créer la convocation
            $convocation = ConvocationParent::create([
                'tenant_id'  => $eleve->tenant_id,
                'eleve_id'   => $eleve->id,
                'motif'      => $motif,
                'message'    => $msg,
                'canal'      => 'sms',
                'statut'     => 'envoyée',
                'envoyee_le' => now(),
                'cree_par'   => 'system',
            ]);

            // Envoyer SMS
            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try { $sms->send($tel, $msg); $convoqués++; } catch (\Throwable) {}
                }
            }
        }

        $this->info("📱 Convocations envoyées : {$convoqués}");

        // 3. Rapport des meilleurs élèves (log)
        $excellents = DiagnosticEleve::where('mention_excellence', true)
            ->with('eleve:id,nom,prenom')
            ->get();
        if ($excellents->count() > 0) {
            $this->info("⭐ Élèves excellents ({$excellents->count()}) :");
            foreach ($excellents as $d) {
                $this->line("   → {$d->eleve?->nom_complet} — {$d->moyenne_generale}/20");
            }
        }

        Log::info('Diagnostic hebdomadaire terminé', $resultats);
        return Command::SUCCESS;
    }
}
```

**Modifier :** `edugestdz/backend/app/Console/Kernel.php`

Ajouter dans `schedule()` :

```php
// Diagnostic niveau élèves — chaque lundi à 6h
$schedule->command('edugest:diagnostic-hebdomadaire')
    ->weekly()
    ->mondays()
    ->at('06:00')
    ->withoutOverlapping()
    ->runInBackground();
```

---

## ÉTAPE 6 — DiagnosticController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/DiagnosticController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticEleve;
use App\Models\PlanRattrapage;
use App\Models\ConvocationParent;
use App\Services\DiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    public function __construct(private DiagnosticService $service) {}

    /**
     * @OA\Get(path="/api/v1/diagnostic/dashboard",
     *   summary="Dashboard diagnostic — vue globale niveau tous élèves",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Response(response=200, description="KPIs diagnostic + top risque + top excellence"))
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->service->getDashboard(config('tenant.current_id'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Get(path="/api/v1/diagnostic/eleves",
     *   summary="Liste des diagnostics par niveau (filtrables)",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Parameter(name="niveau", in="query",
     *     @OA\Schema(type="string", enum={"critique","danger","vigilance","normal","excellent"})),
     *   @OA\Parameter(name="action", in="query",
     *     @OA\Schema(type="string", enum={"rattrapage","convocation","excellence"})),
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="Diagnostics paginés"))
     */
    public function indexDiagnostics(Request $request): JsonResponse
    {
        $query = DiagnosticEleve::with([
            'eleve:id,nom,prenom,niveau_scolaire,photo_url',
        ]);

        if ($request->filled('niveau')) {
            $query->where('niveau_global', $request->niveau);
        }
        if ($request->filled('action')) {
            match ($request->action) {
                'rattrapage'  => $query->where('rattrapage_requis', true),
                'convocation' => $query->where('convocation_requise', true),
                'excellence'  => $query->where('mention_excellence', true),
                default       => null,
            };
        }

        $diagnostics = $query->orderByDesc('score_risque')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $diagnostics,
            'message' => 'Diagnostics récupérés',
        ]);
    }

    /**
     * @OA\Get(path="/api/v1/diagnostic/eleves/{id}",
     *   summary="Diagnostic détaillé d'un élève + historique + recommandations",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Parameter(name="id", in="path", required=true,
     *     @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Diagnostic complet"))
     */
    public function showDiagnostic(string $eleveId): JsonResponse
    {
        $diagnostic = DiagnosticEleve::where('eleve_id', $eleveId)
            ->with(['eleve:id,nom,prenom,niveau_scolaire'])
            ->firstOrFail();

        // Historique des 10 dernières analyses
        $historique = \App\Models\HistoriqueDiagnostic::where('eleve_id', $eleveId)
            ->orderByDesc('analyse_le')
            ->limit(10)
            ->get();

        // Plans de rattrapage actifs
        $rattrapages = PlanRattrapage::where('eleve_id', $eleveId)
            ->whereIn('statut', ['planifié', 'en_cours'])
            ->get();

        // Convocations récentes
        $convocations = ConvocationParent::where('eleve_id', $eleveId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Recommandations automatiques
        $recommandations = $this->genererRecommandations($diagnostic);

        return response()->json([
            'success' => true,
            'data'    => [
                'diagnostic'      => $diagnostic,
                'historique'      => $historique,
                'rattrapages'     => $rattrapages,
                'convocations'    => $convocations,
                'recommandations' => $recommandations,
            ],
        ]);
    }

    /**
     * @OA\Post(path="/api/v1/diagnostic/eleves/{id}/analyser",
     *   summary="Forcer une nouvelle analyse d'un élève",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Parameter(name="id", in="path", required=true,
     *     @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Diagnostic mis à jour"))
     */
    public function analyserEleve(string $eleveId): JsonResponse
    {
        $diagnostic = $this->service->analyserEleve($eleveId);
        return response()->json([
            'success' => true,
            'data'    => $diagnostic->load('eleve'),
            'message' => 'Analyse effectuée',
        ]);
    }

    /**
     * @OA\Post(path="/api/v1/diagnostic/analyser-tous",
     *   summary="Lancer l'analyse de TOUS les élèves du tenant",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Response(response=200, description="Résultats de l'analyse globale"))
     */
    public function analyserTous(): JsonResponse
    {
        $resultats = $this->service->analyserTousLesEleves(config('tenant.current_id'));
        return response()->json([
            'success' => true,
            'data'    => $resultats,
            'message' => "Analyse de {$resultats['total']} élèves terminée",
        ]);
    }

    // ── Plans de rattrapage ───────────────────────────────────────────────────

    /**
     * @OA\Post(path="/api/v1/diagnostic/rattrapages",
     *   summary="Créer un plan de rattrapage",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"eleve_id","matiere","objectifs","date_debut","date_fin"},
     *     @OA\Property(property="eleve_id",    type="string", format="uuid"),
     *     @OA\Property(property="matiere",     type="string", example="Mathématiques"),
     *     @OA\Property(property="objectifs",   type="string"),
     *     @OA\Property(property="programme",   type="string"),
     *     @OA\Property(property="date_debut",  type="string", format="date"),
     *     @OA\Property(property="date_fin",    type="string", format="date"),
     *     @OA\Property(property="enseignant_id",type="string", format="uuid", nullable=true)
     *   )),
     *   @OA\Response(response=201, description="Plan de rattrapage créé"))
     */
    public function creerRattrapage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'      => 'required|uuid|exists:eleves,id',
            'matiere'       => 'required|string|max:100',
            'objectifs'     => 'required|string',
            'programme'     => 'nullable|string',
            'date_debut'    => 'required|date|after_or_equal:today',
            'date_fin'      => 'required|date|after:date_debut',
            'enseignant_id' => 'nullable|uuid|exists:users,id',
        ]);

        $plan = PlanRattrapage::create([
            ...$validated,
            'tenant_id' => config('tenant.current_id'),
            'statut'    => 'planifié',
            'cree_par'  => auth('api')->id(),
        ]);

        // Mettre à jour le diagnostic
        DiagnosticEleve::where('eleve_id', $validated['eleve_id'])
            ->update(['rattrapage_requis' => true]);

        return response()->json([
            'success' => true,
            'data'    => $plan->load('eleve', 'enseignant'),
            'message' => 'Plan de rattrapage créé',
        ], 201);
    }

    /**
     * @OA\Post(path="/api/v1/diagnostic/convocations",
     *   summary="Convoquer les parents d'un élève",
     *   tags={"Diagnostic"}, security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"eleve_id","motif","message"},
     *     @OA\Property(property="eleve_id",       type="string", format="uuid"),
     *     @OA\Property(property="motif",          type="string",
     *       enum={"niveau_critique","absences_excessives","comportement","autre"}),
     *     @OA\Property(property="message",        type="string"),
     *     @OA\Property(property="canal",          type="string",
     *       enum={"sms","whatsapp","email","courrier"}, default="sms"),
     *     @OA\Property(property="rendez_vous_le", type="string", format="date-time", nullable=true)
     *   )),
     *   @OA\Response(response=201, description="Convocation envoyée"))
     */
    public function envoyerConvocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'motif'          => 'required|in:niveau_critique,absences_excessives,comportement,autre',
            'message'        => 'required|string|max:500',
            'canal'          => 'in:sms,whatsapp,email,courrier',
            'rendez_vous_le' => 'nullable|date|after:now',
        ]);

        $eleve = \App\Models\Eleve::with('parents')->findOrFail($validated['eleve_id']);

        // Envoyer selon le canal
        $sent = false;
        if (($validated['canal'] ?? 'sms') === 'sms') {
            $smsService = app(\App\Services\SmsService::class);
            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try { $smsService->send($tel, $validated['message']); $sent = true; }
                    catch (\Throwable) {}
                }
            }
        }

        $convocation = ConvocationParent::create([
            ...$validated,
            'tenant_id'  => config('tenant.current_id'),
            'statut'     => 'envoyée',
            'envoyee_le' => now(),
            'cree_par'   => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $convocation,
            'message' => 'Convocation envoyée' . ($sent ? ' par SMS' : ' (SMS non envoyé)'),
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function genererRecommandations(DiagnosticEleve $diag): array
    {
        $recs = [];

        if ($diag->niveau_global === 'critique') {
            $recs[] = ['priorite' => 'urgente', 'action' => 'Convoquer les parents immédiatement'];
            $recs[] = ['priorite' => 'urgente', 'action' => 'Mettre en place un plan de rattrapage intensif'];
            $recs[] = ['priorite' => 'haute',   'action' => 'Réunion enseignants + directeur'];
        }
        if ($diag->niveau_global === 'danger') {
            $recs[] = ['priorite' => 'haute',   'action' => 'Programmer des séances de rattrapage'];
            $recs[] = ['priorite' => 'haute',   'action' => 'Informer les parents par SMS'];
            $recs[] = ['priorite' => 'normale',  'action' => 'Suivi hebdomadaire des notes'];
        }
        if ($diag->tendance && $diag->tendance <= -3) {
            $recs[] = ['priorite' => 'haute', 'action' => "Chute de {$diag->tendance} points — analyser les causes"];
        }
        if ($diag->nb_absences_mois > 3) {
            $recs[] = ['priorite' => 'haute', 'action' => "Absentéisme ({$diag->nb_absences_mois} absences) — contact parent"];
        }
        foreach ($diag->matieres_en_danger ?? [] as $mat) {
            $recs[] = ['priorite' => 'normale', 'action' => "Rattrapage {$mat['matiere']} (moy: {$mat['moyenne']}/20)"];
        }
        if ($diag->mention_excellence) {
            $recs[] = ['priorite' => 'info', 'action' => 'Féliciter l\'élève — certificat d\'excellence à envisager'];
        }

        return $recs;
    }
}
```

---

## ÉTAPE 7 — Routes

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\DiagnosticController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/diagnostic')->group(function () {
    Route::get('/dashboard',                    [DiagnosticController::class, 'dashboard']);
    Route::get('/eleves',                       [DiagnosticController::class, 'indexDiagnostics']);
    Route::get('/eleves/{id}',                  [DiagnosticController::class, 'showDiagnostic']);
    Route::post('/eleves/{id}/analyser',        [DiagnosticController::class, 'analyserEleve']);
    Route::post('/analyser-tous',               [DiagnosticController::class, 'analyserTous']);
    Route::post('/rattrapages',                 [DiagnosticController::class, 'creerRattrapage']);
    Route::post('/convocations',                [DiagnosticController::class, 'envoyerConvocation']);
});
```

---

## ÉTAPE 8 — Enregistrer l'Observer dans AppServiceProvider

**Modifier :** `edugestdz/backend/app/Providers/AppServiceProvider.php`

Dans `boot()`, ajouter :

```php
// Observer diagnostic — se déclenche à chaque note saisie
\App\Models\Note::observe(\App\Observers\NoteObserver::class);
```

---

## ÉTAPE 9 — Page React DiagnosticPage

**Créer :** `edugestdz/frontend/src/pages/DiagnosticPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { AlertTriangle, Star, TrendingDown, TrendingUp, Users, RefreshCw, BookOpen, Phone } from 'lucide-react';

const api = (path) => fetch(`/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('token')}`,
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
}).then(r => r.json());

const post = (path, body) => fetch(`/api/v1${path}`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  body: JSON.stringify(body),
}).then(r => r.json());

const NIVEAUX = {
  critique:  { color: '#ef4444', bg: '#450a0a', border: '#b91c1c', emoji: '🚨', label: 'CRITIQUE' },
  danger:    { color: '#f87171', bg: '#350808', border: '#991b1b', emoji: '🔴', label: 'DANGER' },
  vigilance: { color: '#fb923c', bg: '#1f1008', border: '#c2410c', emoji: '⚠️', label: 'VIGILANCE' },
  normal:    { color: '#60a5fa', bg: '#0c1a30', border: '#1d4ed8', emoji: '✅', label: 'NORMAL' },
  excellent: { color: '#4ade80', bg: '#0d2515', border: '#16a34a', emoji: '⭐', label: 'EXCELLENT' },
};

export default function DiagnosticPage() {
  const [dashboard, setDashboard] = useState(null);
  const [eleves, setEleves]       = useState([]);
  const [loading, setLoading]     = useState(true);
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [filtreAction, setFiltreAction] = useState('');
  const [selected, setSelected]   = useState(null);
  const [detail, setDetail]       = useState(null);
  const [analysing, setAnalysing] = useState(false);
  const [tab, setTab]             = useState('dashboard');

  useEffect(() => { loadData(); }, [filtreNiveau, filtreAction]);

  const loadData = async () => {
    setLoading(true);
    const params = new URLSearchParams();
    if (filtreNiveau) params.append('niveau', filtreNiveau);
    if (filtreAction) params.append('action', filtreAction);

    const [dash, elvs] = await Promise.all([
      api('/diagnostic/dashboard'),
      api(`/diagnostic/eleves?${params}&per_page=50`),
    ]);
    setDashboard(dash?.data);
    setEleves(elvs?.data?.data ?? []);
    setLoading(false);
  };

  const voirDetail = async (eleveId) => {
    setSelected(eleveId);
    const res = await api(`/diagnostic/eleves/${eleveId}`);
    setDetail(res?.data);
    setTab('detail');
  };

  const analyserTous = async () => {
    setAnalysing(true);
    const res = await post('/diagnostic/analyser-tous', {});
    alert(`✅ Analyse terminée : ${res?.data?.total} élèves analysés`);
    setAnalysing(false);
    loadData();
  };

  const convoquer = async (eleveId) => {
    const msg = prompt('Message de convocation (sera envoyé par SMS) :',
      "Nous vous prions de bien vouloir vous présenter à l'établissement pour discuter du niveau académique de votre enfant.");
    if (!msg) return;
    const res = await post('/diagnostic/convocations', {
      eleve_id: eleveId, motif: 'niveau_critique', message: msg, canal: 'sms',
    });
    alert(res?.message ?? 'Convocation envoyée');
  };

  const StatBox = ({ label, value, color, onClick }) => (
    <div onClick={onClick} style={{
      background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
      padding: '14px', textAlign: 'center', cursor: onClick ? 'pointer' : 'default',
      transition: 'border-color .2s',
    }}
      onMouseEnter={e => onClick && (e.currentTarget.style.borderColor = color)}
      onMouseLeave={e => onClick && (e.currentTarget.style.borderColor = '#1e293b')}
    >
      <div style={{ fontSize: '26px', fontWeight: 900, color }}>{value ?? 0}</div>
      <div style={{ fontSize: '9px', color: '#64748b', marginTop: '2px', textTransform: 'uppercase', letterSpacing: '1px' }}>
        {label}
      </div>
    </div>
  );

  const EleveCard = ({ d }) => {
    const n = NIVEAUX[d.niveau_global] ?? NIVEAUX.normal;
    return (
      <div style={{
        background: n.bg, border: `1px solid ${n.border}`, borderRadius: '10px',
        padding: '12px 16px', marginBottom: '8px',
        display: 'flex', alignItems: 'center', gap: '12px',
      }}>
        <div style={{ fontSize: '22px' }}>{n.emoji}</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800, fontSize: '13px', color: n.color }}>
            {d.eleve?.prenom} {d.eleve?.nom}
            <span style={{ fontSize: '9px', background: n.color + '22', color: n.color,
              padding: '1px 6px', borderRadius: '20px', marginLeft: '8px', fontWeight: 700 }}>
              {n.label}
            </span>
          </div>
          <div style={{ fontSize: '10px', color: '#64748b' }}>
            {d.eleve?.niveau_scolaire} · Moyenne : {d.moyenne_generale ?? '—'}/20
            {d.tendance !== null && (
              <span style={{ color: d.tendance < 0 ? '#f87171' : '#4ade80', marginLeft: '8px' }}>
                {d.tendance > 0 ? '↑' : '↓'} {Math.abs(d.tendance)}pts
              </span>
            )}
          </div>
          {d.matieres_en_danger?.length > 0 && (
            <div style={{ fontSize: '9px', color: '#f87171', marginTop: '2px' }}>
              Difficultés : {d.matieres_en_danger.map(m => m.matiere).join(', ')}
            </div>
          )}
        </div>
        <div style={{ display: 'flex', gap: '6px' }}>
          <button onClick={() => voirDetail(d.eleve_id)}
            style={{ background: '#1e293b', color: '#60a5fa', border: 'none',
              borderRadius: '6px', padding: '5px 10px', fontSize: '10px', cursor: 'pointer', fontWeight: 700 }}>
            Détail
          </button>
          {(d.niveau_global === 'critique' || d.niveau_global === 'danger') && (
            <button onClick={() => convoquer(d.eleve_id)}
              style={{ background: '#450a0a', color: '#f87171', border: 'none',
                borderRadius: '6px', padding: '5px 10px', fontSize: '10px', cursor: 'pointer', fontWeight: 700 }}>
              📱 Convoquer
            </button>
          )}
        </div>
      </div>
    );
  };

  return (
    <div style={{ padding: '24px', background: '#08090f', minHeight: '100vh' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff' }}>
            🔬 Diagnostic de Niveau
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Early Warning System — surveillance continue du niveau académique
          </p>
        </div>
        <button onClick={analyserTous} disabled={analysing} style={{
          background: analysing ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
          color: '#fff', border: 'none', borderRadius: '8px', padding: '10px 16px',
          fontSize: '12px', fontWeight: 700, cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px',
        }}>
          <RefreshCw size={13} />
          {analysing ? 'Analyse en cours...' : 'Analyser tous'}
        </button>
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '4px', marginBottom: '20px' }}>
        {[
          ['dashboard', '📊 Vue globale'],
          ['liste', '👥 Tous les élèves'],
          ['critique', '🚨 Critiques'],
          ['excellence', '⭐ Excellents'],
          ...(detail ? [['detail', '🔍 Détail élève']] : []),
        ].map(([id, label]) => (
          <button key={id} onClick={() => {
            setTab(id);
            if (id === 'critique') { setFiltreNiveau('critique'); setFiltreAction(''); }
            else if (id === 'excellence') { setFiltreNiveau('excellent'); setFiltreAction(''); }
            else if (id === 'liste') { setFiltreNiveau(''); setFiltreAction(''); }
          }} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 14px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{label}</button>
        ))}
      </div>

      {/* Dashboard */}
      {tab === 'dashboard' && dashboard && (
        <div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5,1fr)', gap: '10px', marginBottom: '20px' }}>
            <StatBox label="Excellents" value={dashboard.par_niveau?.excellent} color="#4ade80"
              onClick={() => { setFiltreNiveau('excellent'); setTab('liste'); }} />
            <StatBox label="Normaux"    value={dashboard.par_niveau?.normal}    color="#60a5fa" />
            <StatBox label="Vigilance"  value={dashboard.par_niveau?.vigilance} color="#fb923c"
              onClick={() => { setFiltreNiveau('vigilance'); setTab('liste'); }} />
            <StatBox label="Danger"     value={dashboard.par_niveau?.danger}    color="#f87171"
              onClick={() => { setFiltreNiveau('danger'); setTab('liste'); }} />
            <StatBox label="Critiques"  value={dashboard.par_niveau?.critique}  color="#ef4444"
              onClick={() => { setFiltreNiveau('critique'); setTab('critique'); }} />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            {/* Top à risque */}
            <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '16px' }}>
              <div style={{ fontSize: '11px', color: '#f87171', fontWeight: 800, marginBottom: '12px' }}>
                🔴 TOP 5 ÉLÈVES À RISQUE
              </div>
              {dashboard.top_risque?.map((e, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between',
                  alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #1e293b' }}>
                  <span style={{ fontSize: '12px', color: '#e2e8f0' }}>{e.eleve}</span>
                  <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <span style={{ fontSize: '10px', color: '#64748b' }}>{e.moyenne ?? '—'}/20</span>
                    <span style={{ fontSize: '9px', color: NIVEAUX[e.niveau]?.color, fontWeight: 700 }}>
                      {NIVEAUX[e.niveau]?.emoji} {e.niveau?.toUpperCase()}
                    </span>
                  </div>
                </div>
              ))}
            </div>

            {/* Top excellence */}
            <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '16px' }}>
              <div style={{ fontSize: '11px', color: '#4ade80', fontWeight: 800, marginBottom: '12px' }}>
                ⭐ TOP 5 MEILLEURS ÉLÈVES
              </div>
              {dashboard.top_excellence?.length === 0 && (
                <div style={{ color: '#475569', fontSize: '12px', textAlign: 'center', padding: '20px' }}>
                  Aucun élève excellent détecté
                </div>
              )}
              {dashboard.top_excellence?.map((e, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between',
                  alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #1e293b' }}>
                  <span style={{ fontSize: '12px', color: '#e2e8f0' }}>
                    {i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '⭐'} {e.eleve}
                  </span>
                  <span style={{ fontSize: '11px', color: '#4ade80', fontWeight: 800 }}>
                    {e.moyenne}/20
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Actions requises */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '10px', marginTop: '12px' }}>
            <div style={{ background: '#1a0808', border: '1px solid #b91c1c', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#f87171' }}>{dashboard.actions_requises?.convocations}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>CONVOCATIONS REQUISES</div>
            </div>
            <div style={{ background: '#1f1008', border: '1px solid #c2410c', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#fb923c' }}>{dashboard.actions_requises?.rattrapages}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>RATTRAPAGES REQUIS</div>
            </div>
            <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#4ade80' }}>{dashboard.actions_requises?.excellents}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>MENTIONS EXCELLENCE</div>
            </div>
          </div>
        </div>
      )}

      {/* Liste élèves */}
      {(tab === 'liste' || tab === 'critique' || tab === 'excellence') && (
        <div>
          <div style={{ display: 'flex', gap: '10px', marginBottom: '14px' }}>
            {Object.entries(NIVEAUX).map(([key, val]) => (
              <button key={key} onClick={() => setFiltreNiveau(filtreNiveau === key ? '' : key)} style={{
                background: filtreNiveau === key ? val.bg : '#111318',
                color: filtreNiveau === key ? val.color : '#64748b',
                border: `1px solid ${filtreNiveau === key ? val.border : '#1e293b'}`,
                borderRadius: '20px', padding: '5px 12px', fontSize: '10px',
                fontWeight: 700, cursor: 'pointer',
              }}>{val.emoji} {val.label}</button>
            ))}
          </div>

          {loading ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>Analyse en cours...</div>
          ) : eleves.length === 0 ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>
              Aucun élève dans ce niveau. Lancez une analyse d'abord.
            </div>
          ) : (
            eleves.map(d => <EleveCard key={d.id} d={d} />)
          )}
        </div>
      )}

      {/* Détail élève */}
      {tab === 'detail' && detail && (
        <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '20px' }}>
          <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
            <div style={{ fontSize: '32px' }}>{NIVEAUX[detail.diagnostic?.niveau_global]?.emoji}</div>
            <div>
              <h2 style={{ fontSize: '18px', fontWeight: 900, color: '#fff' }}>
                {detail.diagnostic?.eleve?.prenom} {detail.diagnostic?.eleve?.nom}
              </h2>
              <div style={{ fontSize: '11px', color: '#64748b' }}>
                {detail.diagnostic?.eleve?.niveau_scolaire} ·
                Niveau : {NIVEAUX[detail.diagnostic?.niveau_global]?.label} ·
                Score risque : {detail.diagnostic?.score_risque}/100
              </div>
            </div>
          </div>

          {/* Indicateurs */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '16px' }}>
            {[
              ['Moyenne', `${detail.diagnostic?.moyenne_generale ?? '—'}/20`, '#60a5fa'],
              ['Tendance', detail.diagnostic?.tendance !== null ? `${detail.diagnostic.tendance > 0 ? '+' : ''}${detail.diagnostic.tendance}pts` : '—',
                detail.diagnostic?.tendance < 0 ? '#f87171' : '#4ade80'],
              ['Absences/mois', detail.diagnostic?.nb_absences_mois, '#fb923c'],
              ['Notes < 5', detail.diagnostic?.nb_notes_sous_5, '#f87171'],
            ].map(([label, val, color]) => (
              <div key={label} style={{ background: '#1e293b', borderRadius: '8px', padding: '12px', textAlign: 'center' }}>
                <div style={{ fontSize: '22px', fontWeight: 900, color }}>{val}</div>
                <div style={{ fontSize: '9px', color: '#64748b' }}>{label}</div>
              </div>
            ))}
          </div>

          {/* Recommandations */}
          {detail.recommandations?.length > 0 && (
            <div style={{ marginBottom: '16px' }}>
              <div style={{ fontSize: '11px', color: '#60a5fa', fontWeight: 800, marginBottom: '8px' }}>
                💡 RECOMMANDATIONS
              </div>
              {detail.recommandations.map((r, i) => (
                <div key={i} style={{
                  display: 'flex', gap: '10px', alignItems: 'center',
                  padding: '6px 10px', borderRadius: '6px', marginBottom: '4px',
                  background: r.priorite === 'urgente' ? '#450a0a'
                    : r.priorite === 'haute' ? '#1f1008'
                    : r.priorite === 'info' ? '#0d2515' : '#0c1a30',
                }}>
                  <span style={{ fontSize: '10px', color:
                    r.priorite === 'urgente' ? '#f87171'
                    : r.priorite === 'haute' ? '#fb923c'
                    : r.priorite === 'info' ? '#4ade80' : '#60a5fa',
                    fontWeight: 700, width: '60px', flexShrink: 0 }}>
                    {r.priorite?.toUpperCase()}
                  </span>
                  <span style={{ fontSize: '11px', color: '#94a3b8' }}>{r.action}</span>
                </div>
              ))}
            </div>
          )}

          {/* Matières en difficulté */}
          {detail.diagnostic?.matieres_en_danger?.length > 0 && (
            <div style={{ marginBottom: '16px' }}>
              <div style={{ fontSize: '11px', color: '#f87171', fontWeight: 800, marginBottom: '8px' }}>
                🔴 MATIÈRES EN DIFFICULTÉ
              </div>
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                {detail.diagnostic.matieres_en_danger.map(m => (
                  <div key={m.matiere} style={{
                    background: '#450a0a', border: '1px solid #b91c1c',
                    borderRadius: '8px', padding: '8px 12px',
                  }}>
                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#f87171' }}>{m.matiere}</div>
                    <div style={{ fontSize: '10px', color: '#64748b' }}>Moy : {m.moyenne}/20</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Historique progression */}
          {detail.historique?.length > 0 && (
            <div>
              <div style={{ fontSize: '11px', color: '#60a5fa', fontWeight: 800, marginBottom: '8px' }}>
                📈 HISTORIQUE DE PROGRESSION
              </div>
              <div style={{ display: 'flex', gap: '6px', overflowX: 'auto' }}>
                {detail.historique.slice(0, 8).reverse().map((h, i) => (
                  <div key={i} style={{
                    background: '#1e293b', borderRadius: '6px', padding: '8px',
                    minWidth: '70px', textAlign: 'center', flexShrink: 0,
                  }}>
                    <div style={{ fontSize: '13px', fontWeight: 800,
                      color: NIVEAUX[h.niveau_global]?.color ?? '#60a5fa' }}>
                      {h.moyenne_generale ?? '—'}
                    </div>
                    <div style={{ fontSize: '8px', color: '#64748b' }}>
                      {new Date(h.analyse_le).toLocaleDateString('fr-DZ', { day: '2-digit', month: '2-digit' })}
                    </div>
                    <div style={{ fontSize: '8px', color: NIVEAUX[h.niveau_global]?.color }}>
                      {NIVEAUX[h.niveau_global]?.emoji}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 10 — Ajouter dans App.jsx et Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/App.jsx`

```jsx
import DiagnosticPage from './pages/DiagnosticPage';
<Route path="/diagnostic" element={<DiagnosticPage />} />
```

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

```jsx
{ path: '/diagnostic', icon: '🔬', label: 'Diagnostic Niveau', role: 'admin' },
```

---

## ÉTAPE 11 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Controllers/DiagnosticControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Eleve;
use App\Models\DiagnosticEleve;
use App\Models\Note;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class DiagnosticControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_dashboard_diagnostic(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'total_analyses', 'par_niveau', 'actions_requises', 'top_risque',
            ]]);
    }

    public function test_lister_diagnostics(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/eleves')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_filtrer_par_niveau(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/eleves?niveau=critique')
            ->assertStatus(200);
    }

    public function test_analyser_eleve(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/diagnostic/eleves/{$eleve->id}/analyser")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['niveau_global', 'score_risque']]);
    }

    public function test_analyser_tous(): void
    {
        Eleve::factory()->count(3)->create(['statut' => 'actif']);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/analyser-tous')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total']]);
    }

    public function test_creer_plan_rattrapage(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/rattrapages', [
                'eleve_id'   => $eleve->id,
                'matiere'    => 'Mathématiques',
                'objectifs'  => 'Maîtriser les équations du 2ème degré',
                'programme'  => '3 séances de 2h par semaine',
                'date_debut' => now()->addDay()->format('Y-m-d'),
                'date_fin'   => now()->addMonth()->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_envoyer_convocation(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/convocations', [
                'eleve_id' => $eleve->id,
                'motif'    => 'niveau_critique',
                'message'  => 'Veuillez vous présenter à l\'établissement.',
                'canal'    => 'sms',
            ])
            ->assertStatus(201);
    }

    public function test_detail_eleve_avec_diagnostic(): void
    {
        $eleve = Eleve::factory()->create();
        DiagnosticEleve::create([
            'tenant_id'      => $eleve->tenant_id,
            'eleve_id'       => $eleve->id,
            'niveau_global'  => 'normal',
            'score_risque'   => 25,
            'moyenne_generale' => 12.5,
            'derniere_analyse' => now(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/diagnostic/eleves/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'diagnostic', 'historique', 'rattrapages', 'recommandations',
            ]]);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/diagnostic/dashboard')->assertStatus(401);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# 1. Migration
create: database/migrations/2026_07_04_200000_create_diagnostic_niveau_tables.php
php artisan migrate

# 2. Models (4 fichiers)
create: app/Models/DiagnosticEleve.php
create: app/Models/PlanRattrapage.php
create: app/Models/ConvocationParent.php
create: app/Models/HistoriqueDiagnostic.php

# 3. Service
create: app/Services/DiagnosticService.php

# 4. Observer (remplace ou complète NoteObserver existant)
create: app/Observers/NoteObserver.php

# 5. Commande scheduler
create: app/Console/Commands/DiagnosticHebdomadaireCommand.php

# 6. Modifier Kernel.php (ajouter scheduler lundi 6h)
modify: app/Console/Kernel.php

# 7. Controller
create: app/Http/Controllers/Api/V1/DiagnosticController.php

# 8. Routes
modify: routes/api.php → ajouter groupe /v1/diagnostic (7 routes)

# 9. AppServiceProvider — enregistrer NoteObserver
modify: app/Providers/AppServiceProvider.php

# 10. Frontend
create: frontend/src/pages/DiagnosticPage.jsx
modify: frontend/src/App.jsx → import + route /diagnostic
modify: frontend/src/components/Sidebar.jsx → lien Diagnostic Niveau

# 11. Tests
create: tests/Feature/Controllers/DiagnosticControllerTest.php

# 12. Lancer les tests
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 9 nouveaux tests verts

# 13. Commit & PR
git add .
git commit -m "feat: Module Diagnostic Niveau — Early Warning System (5 niveaux + rattrapage + convocation + historique + 9 tests)"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_DIAGNOSTIC_NIVEAU_ELEVES.md — 11 étapes dans l'ordre.

RÈGLES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. Si NoteObserver existe déjà (mission push) → fusionner les deux (ne pas écraser).
4. Si DiagnosticService.analyserEleve() échoue sur un élève sans notes →
   retourner niveau "normal" et score 0 (pas d'exception).
5. Les 4 tables doivent être créées en une seule migration.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
