# 🤖 MISSION DEEPSEEK — Module LMS (Learning Management System)
## EduGest DZ · Branche : develop · 7 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 435 ✅ · 0 régression

---

## CONTEXTE — Recherche effectuée

### Ce que font les meilleurs LMS mondiaux (Moodle, Canvas, Teachmint)

Fonctionnalités essentielles identifiées :
```
CONTENU      : Vidéos · PDF · Slides · Fichiers audio · Liens externes
ORGANISATION : Cours → Chapitres → Leçons (hiérarchie 3 niveaux)
ÉVALUATION   : QCM · Vrai/Faux · Questions ouvertes · Devoirs à rendre
PROGRESSION  : Suivi par élève · % complété · Temps passé
CERTIFICATS  : Génération PDF auto à la fin du cours
ACCÈS        : Par groupe · Par niveau scolaire · Par élève individuel
MOBILE       : Contenu lisible sur app React Native parent/élève
```

### Adapté au contexte EduGest DZ (centres de cours Algérie)
```
Un centre de cours particuliers à Oran utilise déjà :
  - Séances présentielles gérées dans EduGest
  - Notes saisies par l'enseignant
  - Bulletins générés

Ce qu'on ajoute avec le LMS :
  - L'enseignant publie des fiches de cours PDF
  - L'enseignant publie des vidéos explicatives (YouTube/Vimeo embed ou upload direct)
  - Les élèves font des exercices QCM depuis l'app mobile
  - L'enseignant voit qui a regardé quoi et combien de temps
  - Certificat de complétion si l'élève finit le cours
```

### Ce qui EXISTE déjà (ne pas recréer)
- `CoursController.php` — gestion des cours (planning)
- `EvaluationController.php` — notes et évaluations en présentiel
- `BulletinController.php` — bulletins PDF
- `GroupesController.php` — groupes d'élèves
- Table `cours`, `seances`, `evaluations`, `notes` — EXISTER déjà

### Ce qu'on construit (LMS = tout nouveau)
- **Cours LMS** (différent des "cours" planning) : contenu pédagogique en ligne
- **Chapitres** et **leçons** hiérarchisés
- **Ressources** : PDF, vidéo embed, audio, lien externe
- **Quiz** : QCM avec correction automatique
- **Devoirs** : soumission fichier par l'élève
- **Progression** : % complété par élève
- **Certificats** : PDF généré quand cours complété

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Multi-tenant — chaque école a ses propres cours LMS
4. Tables LMS préfixées `lms_` pour éviter conflit avec `cours` existant
5. Ne pas modifier les contrôleurs existants
6. Réutiliser DomPDF (barryvdh/laravel-dompdf) déjà installé

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : 7 tables LMS

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_100000_create_lms_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Cours LMS ─────────────────────────────────────────────────
        Schema::create('lms_cours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('enseignant_id');       // auteur du cours
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('matiere')->nullable();
            $table->jsonb('niveaux_cibles')->default('[]');
            // ex: ["3AS","2AS"] — niveaux scolaires concernés
            $table->string('langue')->default('ar'); // ar | fr | en
            $table->string('duree_estimee')->nullable(); // ex: "3h30"
            $table->string('image_url')->nullable();   // vignette du cours
            $table->boolean('publie')->default(false);
            $table->boolean('certificat_actif')->default(true);
            $table->integer('seuil_completion')->default(80);
            // % de leçons à terminer pour obtenir le certificat
            $table->integer('nb_chapitres')->default(0);
            $table->integer('nb_lecons')->default(0);
            $table->integer('nb_inscrits')->default(0);
            $table->decimal('note_moyenne', 3, 1)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'publie'],      'idx_lms_cours_publie');
            $table->index(['tenant_id', 'enseignant_id'],'idx_lms_cours_ens');
            $table->index(['tenant_id', 'matiere'],     'idx_lms_cours_matiere');
        });

        // ── 2. Chapitres ──────────────────────────────────────────────────
        Schema::create('lms_chapitres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('cours_id');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(1);
            $table->boolean('publie')->default(true);
            $table->timestamps();

            $table->foreign('cours_id')->references('id')->on('lms_cours')->onDelete('cascade');
            $table->index(['cours_id', 'ordre'], 'idx_lms_chapitre_ordre');
        });

        // ── 3. Leçons ─────────────────────────────────────────────────────
        Schema::create('lms_lecons', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('chapitre_id');
            $table->string('titre');
            $table->text('contenu')->nullable();   // texte enrichi (HTML)
            $table->string('type')->default('texte');
            // Valeurs : texte | video | pdf | audio | lien | quiz | devoir
            $table->string('ressource_url')->nullable();
            // Pour video : URL YouTube/Vimeo embed
            // Pour pdf   : chemin du fichier stocké
            // Pour lien  : URL externe
            $table->string('ressource_nom')->nullable(); // nom affiché
            $table->integer('duree_minutes')->nullable(); // durée estimée
            $table->integer('ordre')->default(1);
            $table->boolean('gratuite')->default(false); // aperçu sans inscription
            $table->boolean('publiee')->default(true);
            $table->timestamps();

            $table->foreign('chapitre_id')->references('id')->on('lms_chapitres')->onDelete('cascade');
            $table->index(['chapitre_id', 'ordre'], 'idx_lms_lecon_ordre');
        });

        // ── 4. Quiz et questions ──────────────────────────────────────────
        Schema::create('lms_quiz', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lecon_id');             // quiz lié à une leçon
            $table->string('titre');
            $table->integer('nb_questions')->default(0);
            $table->integer('duree_minutes')->default(30);
            $table->integer('seuil_reussite')->default(60); // % pour réussir
            $table->integer('nb_tentatives_max')->default(3);
            $table->boolean('correction_immediate')->default(true);
            $table->boolean('ordre_aleatoire')->default(false);
            $table->timestamps();

            $table->foreign('lecon_id')->references('id')->on('lms_lecons')->onDelete('cascade');
        });

        Schema::create('lms_questions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('quiz_id');
            $table->string('type')->default('qcm');
            // Valeurs : qcm | vrai_faux | reponse_courte | correspondance
            $table->text('enonce');
            $table->jsonb('options')->default('[]');
            // Pour QCM   : [{"id":"a","texte":"...","correct":true},...]
            // Pour VF    : {"bonne_reponse": true}
            // Pour texte : {"reponse_attendue": "..."}
            $table->text('explication')->nullable(); // explication après correction
            $table->integer('points')->default(1);
            $table->integer('ordre')->default(1);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('lms_quiz')->onDelete('cascade');
        });

        // ── 5. Inscriptions aux cours LMS ──────────────────────────────────
        Schema::create('lms_inscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('cours_id');
            $table->uuid('eleve_id');
            $table->uuid('tenant_id');
            $table->enum('statut', ['actif','suspendu','termine'])->default('actif');
            $table->integer('progression_pct')->default(0); // 0-100
            $table->integer('nb_lecons_completees')->default(0);
            $table->integer('temps_total_minutes')->default(0);
            $table->timestamp('derniere_activite')->nullable();
            $table->timestamp('complete_le')->nullable();
            $table->string('certificat_url')->nullable(); // PDF du certificat
            $table->timestamps();

            $table->unique(['cours_id', 'eleve_id'], 'uniq_lms_insc');
            $table->foreign('cours_id')->references('id')->on('lms_cours')->onDelete('cascade');
            $table->index(['eleve_id', 'statut'],  'idx_lms_insc_eleve');
            $table->index(['tenant_id', 'cours_id'],'idx_lms_insc_tenant');
        });

        // ── 6. Progression par leçon ─────────────────────────────────────
        Schema::create('lms_progression', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inscription_id');
            $table->uuid('lecon_id');
            $table->uuid('eleve_id');
            $table->boolean('completee')->default(false);
            $table->integer('temps_passe_secondes')->default(0);
            $table->integer('nb_vues')->default(0);
            $table->timestamp('completee_le')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'lecon_id'], 'uniq_lms_prog');
            $table->index(['eleve_id', 'completee'], 'idx_lms_prog_eleve');
        });

        // ── 7. Tentatives de quiz ─────────────────────────────────────────
        Schema::create('lms_tentatives_quiz', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('quiz_id');
            $table->uuid('eleve_id');
            $table->uuid('inscription_id');
            $table->integer('score')->default(0);   // score obtenu (points)
            $table->integer('score_max')->default(0);
            $table->integer('pourcentage')->default(0);
            $table->boolean('reussi')->default(false);
            $table->integer('duree_secondes')->default(0);
            $table->jsonb('reponses')->default('{}');
            // {"question_id": "reponse_donnee_id", ...}
            $table->integer('numero_tentative')->default(1);
            $table->timestamp('debut_le');
            $table->timestamp('fin_le')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('lms_quiz')->onDelete('cascade');
            $table->index(['quiz_id', 'eleve_id'],       'idx_lms_tentative_quiz');
            $table->index(['eleve_id', 'reussi'],        'idx_lms_tentative_eleve');
        });

        // ── 8. Devoirs soumis ────────────────────────────────────────────
        Schema::create('lms_devoirs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lecon_id');
            $table->uuid('eleve_id');
            $table->uuid('inscription_id');
            $table->string('fichier_url')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->text('commentaire_eleve')->nullable();
            $table->enum('statut', ['soumis','corrige','retourne'])->default('soumis');
            $table->decimal('note', 5, 2)->nullable();     // note sur note_max
            $table->decimal('note_max', 5, 2)->default(20);
            $table->text('feedback_enseignant')->nullable();
            $table->uuid('corrige_par')->nullable();
            $table->timestamp('corrige_le')->nullable();
            $table->timestamp('soumis_le');
            $table->timestamps();

            $table->foreign('lecon_id')->references('id')->on('lms_lecons')->onDelete('cascade');
            $table->index(['lecon_id', 'eleve_id'],  'idx_lms_devoir_lecon');
            $table->index(['eleve_id', 'statut'],    'idx_lms_devoir_eleve');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_devoirs');
        Schema::dropIfExists('lms_tentatives_quiz');
        Schema::dropIfExists('lms_progression');
        Schema::dropIfExists('lms_inscriptions');
        Schema::dropIfExists('lms_questions');
        Schema::dropIfExists('lms_quiz');
        Schema::dropIfExists('lms_lecons');
        Schema::dropIfExists('lms_chapitres');
        Schema::dropIfExists('lms_cours');
    }
};
```

---

## ÉTAPE 2 — Models (9 fichiers)

**Créer :** `edugestdz/backend/app/Models/LmsCours.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCours extends Model
{
    use HasUuids, SoftDeletes;
    protected $table = 'lms_cours';
    protected $fillable = [
        'tenant_id','enseignant_id','titre','description','matiere',
        'niveaux_cibles','langue','duree_estimee','image_url',
        'publie','certificat_actif','seuil_completion',
        'nb_chapitres','nb_lecons','nb_inscrits','note_moyenne',
    ];
    protected $casts = [
        'niveaux_cibles'   => 'array',
        'publie'           => 'boolean',
        'certificat_actif' => 'boolean',
    ];

    public function enseignant()    { return $this->belongsTo(User::class, 'enseignant_id'); }
    public function chapitres()     { return $this->hasMany(LmsChapitre::class, 'cours_id')->orderBy('ordre'); }
    public function inscriptions()  { return $this->hasMany(LmsInscription::class, 'cours_id'); }
    public function scopePublie($q) { return $q->where('publie', true); }

    public function getNbLeconsRealAttribute(): int
    {
        return $this->chapitres()->withCount('lecons')->get()->sum('lecons_count');
    }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsChapitre.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsChapitre extends Model
{
    use HasUuids;
    protected $table = 'lms_chapitres';
    protected $fillable = ['cours_id','titre','description','ordre','publie'];
    protected $casts = ['publie' => 'boolean'];

    public function cours()  { return $this->belongsTo(LmsCours::class, 'cours_id'); }
    public function lecons() { return $this->hasMany(LmsLecon::class, 'chapitre_id')->orderBy('ordre'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsLecon.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsLecon extends Model
{
    use HasUuids;
    protected $table = 'lms_lecons';
    protected $fillable = [
        'chapitre_id','titre','contenu','type',
        'ressource_url','ressource_nom','duree_minutes',
        'ordre','gratuite','publiee',
    ];
    protected $casts = ['gratuite' => 'boolean', 'publiee' => 'boolean'];

    public const TYPES = [
        'texte'  => ['label' => 'Texte / Cours',    'icon' => '📄'],
        'video'  => ['label' => 'Vidéo',            'icon' => '🎥'],
        'pdf'    => ['label' => 'Document PDF',     'icon' => '📑'],
        'audio'  => ['label' => 'Audio',            'icon' => '🎵'],
        'lien'   => ['label' => 'Lien externe',     'icon' => '🔗'],
        'quiz'   => ['label' => 'Quiz / Exercices', 'icon' => '✏️'],
        'devoir' => ['label' => 'Devoir à rendre',  'icon' => '📝'],
    ];

    public function chapitre()    { return $this->belongsTo(LmsChapitre::class, 'chapitre_id'); }
    public function quiz()        { return $this->hasOne(LmsQuiz::class, 'lecon_id'); }
    public function progressions(){ return $this->hasMany(LmsProgression::class, 'lecon_id'); }
    public function devoirs()     { return $this->hasMany(LmsDevoir::class, 'lecon_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsQuiz.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsQuiz extends Model
{
    use HasUuids;
    protected $table = 'lms_quiz';
    protected $fillable = [
        'lecon_id','titre','nb_questions','duree_minutes',
        'seuil_reussite','nb_tentatives_max','correction_immediate','ordre_aleatoire',
    ];
    protected $casts = ['correction_immediate' => 'boolean', 'ordre_aleatoire' => 'boolean'];

    public function lecon()     { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function questions() { return $this->hasMany(LmsQuestion::class, 'quiz_id')->orderBy('ordre'); }
    public function tentatives(){ return $this->hasMany(LmsTentativeQuiz::class, 'quiz_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsQuestion.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsQuestion extends Model
{
    use HasUuids;
    protected $table = 'lms_questions';
    protected $fillable = ['quiz_id','type','enonce','options','explication','points','ordre'];
    protected $casts = ['options' => 'array'];

    public function quiz() { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }

    public function verifierReponse(string $reponseId): bool
    {
        if ($this->type === 'qcm') {
            $option = collect($this->options)->firstWhere('id', $reponseId);
            return (bool) ($option['correct'] ?? false);
        }
        if ($this->type === 'vrai_faux') {
            return (string) $reponseId === (string) ($this->options['bonne_reponse'] ?? '');
        }
        return false;
    }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsInscription.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsInscription extends Model
{
    use HasUuids;
    protected $table = 'lms_inscriptions';
    protected $fillable = [
        'cours_id','eleve_id','tenant_id','statut',
        'progression_pct','nb_lecons_completees','temps_total_minutes',
        'derniere_activite','complete_le','certificat_url',
    ];
    protected $casts = ['derniere_activite' => 'datetime', 'complete_le' => 'datetime'];

    public function cours()      { return $this->belongsTo(LmsCours::class, 'cours_id'); }
    public function eleve()      { return $this->belongsTo(Eleve::class, 'eleve_id'); }
    public function progressions(){ return $this->hasMany(LmsProgression::class, 'inscription_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsProgression.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsProgression extends Model
{
    use HasUuids;
    protected $table = 'lms_progression';
    protected $fillable = [
        'inscription_id','lecon_id','eleve_id',
        'completee','temps_passe_secondes','nb_vues','completee_le',
    ];
    protected $casts = ['completee' => 'boolean', 'completee_le' => 'datetime'];

    public function lecon()       { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function inscription() { return $this->belongsTo(LmsInscription::class, 'inscription_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsTentativeQuiz.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsTentativeQuiz extends Model
{
    use HasUuids;
    protected $table = 'lms_tentatives_quiz';
    protected $fillable = [
        'quiz_id','eleve_id','inscription_id',
        'score','score_max','pourcentage','reussi',
        'duree_secondes','reponses','numero_tentative','debut_le','fin_le',
    ];
    protected $casts = ['reponses' => 'array', 'reussi' => 'boolean', 'debut_le' => 'datetime', 'fin_le' => 'datetime'];

    public function quiz()  { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }
    public function eleve() { return $this->belongsTo(Eleve::class, 'eleve_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/LmsDevoir.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsDevoir extends Model
{
    use HasUuids;
    protected $table = 'lms_devoirs';
    protected $fillable = [
        'lecon_id','eleve_id','inscription_id',
        'fichier_url','fichier_nom','commentaire_eleve',
        'statut','note','note_max','feedback_enseignant',
        'corrige_par','corrige_le','soumis_le',
    ];
    protected $casts = ['corrige_le' => 'datetime', 'soumis_le' => 'datetime'];

    public function lecon()  { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function eleve()  { return $this->belongsTo(Eleve::class, 'eleve_id'); }
}
```

---

## ÉTAPE 3 — LmsService

**Créer :** `edugestdz/backend/app/Services/LmsService.php`

```php
<?php

namespace App\Services;

use App\Models\LmsCours;
use App\Models\LmsInscription;
use App\Models\LmsProgression;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsTentativeQuiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class LmsService
{
    public function __construct(
        private ParentNotificationService $notif
    ) {}

    // ══════════════════════════════════════════════════════════
    // INSCRIPTION
    // ══════════════════════════════════════════════════════════

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

        // Incrémenter le compteur d'inscrits
        if ($inscription->wasRecentlyCreated) {
            $cours->increment('nb_inscrits');

            // Notifier les parents
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

    // ══════════════════════════════════════════════════════════
    // PROGRESSION — Marquer une leçon comme vue/complétée
    // ══════════════════════════════════════════════════════════

    public function marquerLeconComplete(
        string $inscriptionId,
        string $leconId,
        int    $tempsSecondes = 0
    ): array {
        $inscription = LmsInscription::with('cours.chapitres.lecons')->findOrFail($inscriptionId);

        $progression = LmsProgression::updateOrCreate(
            ['inscription_id' => $inscriptionId, 'lecon_id' => $leconId],
            [
                'eleve_id'             => $inscription->eleve_id,
                'completee'            => true,
                'temps_passe_secondes' => $tempsSecondes,
                'completee_le'         => now(),
                'nb_vues'              => DB::raw('nb_vues + 1'),
            ]
        );

        // Recalculer la progression globale
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

        // Total de leçons publiées dans le cours
        $totalLecons = $cours->chapitres
            ->flatMap(fn($c) => $c->lecons->where('publiee', true))
            ->count();

        if ($totalLecons === 0) return ['pct' => 0, 'cours_complete' => false];

        // Leçons complétées par cet élève
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

        // Cours complété si au-dessus du seuil
        if ($pct >= $cours->seuil_completion && !$inscription->complete_le) {
            $update['statut']      = 'termine';
            $update['complete_le'] = now();
            $coursComplete         = true;

            // Générer le certificat si activé
            if ($cours->certificat_actif) {
                $certificatUrl = $this->genererCertificat($inscription);
                $update['certificat_url'] = $certificatUrl;
            }

            // Notifier les parents
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

    // ══════════════════════════════════════════════════════════
    // QUIZ — Soumettre et corriger automatiquement
    // ══════════════════════════════════════════════════════════

    public function soumettreTentativeQuiz(
        string $quizId,
        string $eleveId,
        string $inscriptionId,
        array  $reponses,  // ['question_id' => 'option_id', ...]
        int    $dureeSecondes
    ): LmsTentativeQuiz {
        $quiz      = LmsQuiz::with('questions')->findOrFail($quizId);
        $questions = $quiz->questions;

        // Vérifier le nombre de tentatives
        $nbTentatives = LmsTentativeQuiz::where('quiz_id', $quizId)
            ->where('eleve_id', $eleveId)->count();

        if ($nbTentatives >= $quiz->nb_tentatives_max) {
            throw new \RuntimeException("Nombre maximum de tentatives atteint ({$quiz->nb_tentatives_max}).");
        }

        // Calculer le score
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

        // Si quiz réussi → marquer la leçon comme complétée
        if ($reussi) {
            $lecon = $quiz->lecon;
            $this->marquerLeconComplete($inscriptionId, $lecon->id, $dureeSecondes);
        }

        return $tentative;
    }

    // ══════════════════════════════════════════════════════════
    // CERTIFICAT PDF
    // ══════════════════════════════════════════════════════════

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

    // ══════════════════════════════════════════════════════════
    // DASHBOARD LMS
    // ══════════════════════════════════════════════════════════

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
```

---

## ÉTAPE 4 — Vue Blade : Certificat PDF

**Créer :** `edugestdz/backend/resources/views/pdf/lms-certificat.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:DejaVu Sans, sans-serif; background:#fff; width:297mm; height:210mm; }
  .certificat {
    width:100%; height:100%; padding:20mm 25mm;
    border:8mm solid #1e3a5f;
    position:relative;
  }
  .certificat::before {
    content:'';
    position:absolute; inset:5mm;
    border:2px solid #2563eb;
  }
  .header { text-align:center; margin-bottom:8mm; }
  .school { font-size:14pt; color:#64748b; margin-bottom:2mm; }
  .title  { font-size:28pt; font-weight:bold; color:#1e3a5f; margin-bottom:2mm; }
  .subtitle { font-size:11pt; color:#475569; letter-spacing:3px; text-transform:uppercase; }
  .divider { border:none; border-top:2px solid #e2e8f0; margin:6mm 0; }
  .content { text-align:center; }
  .certifie { font-size:12pt; color:#64748b; margin-bottom:3mm; }
  .nom-eleve { font-size:24pt; font-weight:bold; color:#1e3a5f; margin-bottom:2mm; font-style:italic; }
  .for-completing { font-size:11pt; color:#64748b; margin-bottom:3mm; }
  .nom-cours { font-size:18pt; font-weight:bold; color:#2563eb; margin-bottom:2mm; }
  .details   { font-size:10pt; color:#94a3b8; margin-bottom:8mm; }
  .footer    { display:flex; justify-content:space-between; align-items:flex-end; margin-top:10mm; }
  .sig-bloc  { text-align:center; }
  .sig-line  { border-bottom:1px solid #1e3a5f; width:50mm; margin-bottom:2mm; }
  .sig-nom   { font-size:10pt; color:#475569; }
  .sig-role  { font-size:9pt; color:#94a3b8; }
  .badge     {
    width:25mm; height:25mm; border-radius:50%;
    background:#1e3a5f; display:flex; align-items:center;
    justify-content:center; margin:0 auto 2mm;
  }
  .badge-txt { color:#fff; font-size:7pt; text-align:center; font-weight:bold; line-height:1.3; }
  .date-bloc { text-align:center; }
  .date-val  { font-size:11pt; color:#1e3a5f; font-weight:bold; }
  .watermark {
    position:absolute; top:50%; left:50%;
    transform:translate(-50%,-50%) rotate(-30deg);
    font-size:60pt; color:#f0f9ff; opacity:.5;
    font-weight:900; pointer-events:none; z-index:0;
  }
</style>
</head>
<body>
<div class="certificat">
  <div class="watermark">🎓</div>
  <div class="header">
    <div class="school">🎓 {{ $cours->tenant_id ? 'EduGest DZ' : 'EduGest DZ' }}</div>
    <div class="title">Certificat de Complétion</div>
    <div class="subtitle">Certificate of Completion · شهادة إتمام</div>
  </div>
  <hr class="divider">
  <div class="content">
    <div class="certifie">Ce certificat est décerné à</div>
    <div class="nom-eleve">{{ $eleve->prenom }} {{ $eleve->nom }}</div>
    <div class="for-completing">pour avoir complété avec succès le cours</div>
    <div class="nom-cours">{{ $cours->titre }}</div>
    <div class="details">
      Matière : {{ $cours->matiere ?? '—' }} &nbsp;·&nbsp;
      Durée : {{ $cours->duree_estimee ?? 'N/A' }} &nbsp;·&nbsp;
      Progression : {{ $inscription->progression_pct }}%
    </div>
  </div>
  <div class="footer">
    <div class="sig-bloc">
      <div class="sig-line"></div>
      <div class="sig-nom">{{ $cours->enseignant->nom ?? 'Enseignant' }} {{ $cours->enseignant->prenom ?? '' }}</div>
      <div class="sig-role">Responsable du cours</div>
    </div>
    <div>
      <div class="badge"><div class="badge-txt">CERTIFIÉ<br>EDUGEST<br>DZ</div></div>
    </div>
    <div class="date-bloc">
      <div class="sig-line"></div>
      <div class="date-val">{{ $inscription->complete_le?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
      <div class="sig-role">Date d'obtention</div>
    </div>
  </div>
</div>
</body>
</html>
```

---

## ÉTAPE 5 — LmsController

**Créer :**
`edugestdz/backend/app/Http/Controllers/Api/V1/LmsController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LmsCours;
use App\Models\LmsChapitre;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsInscription;
use App\Models\LmsProgression;
use App\Models\LmsTentativeQuiz;
use App\Models\LmsDevoir;
use App\Services\LmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class LmsController extends Controller
{
    public function __construct(private LmsService $lms) {}

    // ════════════════════════════════════════════════════════
    // COURS
    // ════════════════════════════════════════════════════════

    public function indexCours(Request $request): JsonResponse
    {
        $cours = LmsCours::with('enseignant:id,nom,prenom')
            ->withCount('inscriptions')
            ->when($request->filled('matiere'),  fn($q) => $q->where('matiere', $request->matiere))
            ->when($request->filled('publie'),   fn($q) => $q->where('publie', (bool) $request->publie))
            ->when($request->filled('niveau'),   fn($q) => $q->whereJsonContains('niveaux_cibles', $request->niveau))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $cours,
            'stats'   => $this->lms->getDashboard(),
        ]);
    }

    public function storeCours(Request $request): JsonResponse
    {
        $v = $request->validate([
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string',
            'matiere'         => 'nullable|string|max:100',
            'niveaux_cibles'  => 'array',
            'langue'          => 'in:ar,fr,en',
            'duree_estimee'   => 'nullable|string|max:20',
            'seuil_completion'=> 'integer|min:1|max:100',
            'certificat_actif'=> 'boolean',
        ]);

        $cours = LmsCours::create([
            ...$v,
            'tenant_id'      => config('tenant.current_id'),
            'enseignant_id'  => auth('api')->id(),
            'publie'         => false,
        ]);

        return response()->json(['success' => true, 'data' => $cours, 'message' => 'Cours créé'], 201);
    }

    public function showCours(string $id): JsonResponse
    {
        $cours = LmsCours::with([
            'enseignant:id,nom,prenom',
            'chapitres.lecons',
            'inscriptions' => fn($q) => $q->limit(5),
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $cours]);
    }

    public function updateCours(Request $request, string $id): JsonResponse
    {
        $cours = LmsCours::findOrFail($id);
        $cours->update($request->only([
            'titre','description','matiere','niveaux_cibles','langue',
            'duree_estimee','seuil_completion','certificat_actif','publie',
        ]));
        return response()->json(['success' => true, 'data' => $cours->fresh()]);
    }

    public function publierCours(string $id): JsonResponse
    {
        $cours = LmsCours::findOrFail($id);

        // Vérifier qu'il y a au moins un chapitre et une leçon
        $nbLecons = $cours->chapitres()->withCount('lecons')->get()->sum('lecons_count');
        if ($nbLecons === 0) {
            return response()->json(['success' => false, 'message' => 'Ajouter au moins 1 chapitre et 1 leçon avant de publier.'], 422);
        }

        $cours->update([
            'publie'       => !$cours->publie,
            'nb_chapitres' => $cours->chapitres()->count(),
            'nb_lecons'    => $nbLecons,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $cours->fresh(),
            'message' => $cours->publie ? 'Cours publié ✅' : 'Cours dépublié',
        ]);
    }

    // ════════════════════════════════════════════════════════
    // CHAPITRES
    // ════════════════════════════════════════════════════════

    public function storeChapitre(Request $request, string $coursId): JsonResponse
    {
        $v = $request->validate([
            'titre'      => 'required|string|max:200',
            'description'=> 'nullable|string',
            'ordre'      => 'integer|min:1',
        ]);

        $ordre    = $v['ordre'] ?? LmsChapitre::where('cours_id', $coursId)->max('ordre') + 1;
        $chapitre = LmsChapitre::create([...$v, 'cours_id' => $coursId, 'ordre' => $ordre]);
        LmsCours::find($coursId)?->increment('nb_chapitres');

        return response()->json(['success' => true, 'data' => $chapitre], 201);
    }

    public function updateChapitre(Request $request, string $id): JsonResponse
    {
        $chapitre = LmsChapitre::findOrFail($id);
        $chapitre->update($request->only(['titre','description','ordre','publie']));
        return response()->json(['success' => true, 'data' => $chapitre->fresh()]);
    }

    public function deleteChapitre(string $id): JsonResponse
    {
        $chapitre = LmsChapitre::findOrFail($id);
        LmsCours::find($chapitre->cours_id)?->decrement('nb_chapitres');
        $chapitre->delete();
        return response()->json(['success' => true, 'message' => 'Chapitre supprimé']);
    }

    // ════════════════════════════════════════════════════════
    // LEÇONS
    // ════════════════════════════════════════════════════════

    public function storeLecon(Request $request, string $chapitreId): JsonResponse
    {
        $v = $request->validate([
            'titre'         => 'required|string|max:200',
            'type'          => 'required|in:texte,video,pdf,audio,lien,quiz,devoir',
            'contenu'       => 'nullable|string',
            'ressource_url' => 'nullable|string|max:500',
            'ressource_nom' => 'nullable|string|max:200',
            'duree_minutes' => 'nullable|integer|min:1',
            'ordre'         => 'integer|min:1',
            'gratuite'      => 'boolean',
        ]);

        $ordre = $v['ordre'] ?? LmsLecon::where('chapitre_id', $chapitreId)->max('ordre') + 1;
        $lecon = LmsLecon::create([...$v, 'chapitre_id' => $chapitreId, 'ordre' => $ordre]);

        return response()->json(['success' => true, 'data' => $lecon], 201);
    }

    public function updateLecon(Request $request, string $id): JsonResponse
    {
        $lecon = LmsLecon::findOrFail($id);
        $lecon->update($request->only([
            'titre','contenu','type','ressource_url','ressource_nom',
            'duree_minutes','ordre','gratuite','publiee',
        ]));
        return response()->json(['success' => true, 'data' => $lecon->fresh()]);
    }

    public function uploadFichierLecon(Request $request, string $id): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|max:51200']); // 50MB max
        $lecon   = LmsLecon::findOrFail($id);
        $path    = $request->file('fichier')->store("lms/lecons/{$lecon->id}", 'public');
        $lecon->update([
            'ressource_url' => Storage::url($path),
            'ressource_nom' => $request->file('fichier')->getClientOriginalName(),
        ]);
        return response()->json(['success' => true, 'data' => $lecon->fresh()]);
    }

    // ════════════════════════════════════════════════════════
    // QUIZ
    // ════════════════════════════════════════════════════════

    public function storeQuiz(Request $request, string $leconId): JsonResponse
    {
        $v = $request->validate([
            'titre'               => 'required|string|max:200',
            'duree_minutes'       => 'integer|min:1',
            'seuil_reussite'      => 'integer|min:1|max:100',
            'nb_tentatives_max'   => 'integer|min:1|max:10',
            'correction_immediate'=> 'boolean',
            'ordre_aleatoire'     => 'boolean',
        ]);

        $quiz = LmsQuiz::create([...$v, 'lecon_id' => $leconId]);
        return response()->json(['success' => true, 'data' => $quiz], 201);
    }

    public function storeQuestion(Request $request, string $quizId): JsonResponse
    {
        $v = $request->validate([
            'type'        => 'required|in:qcm,vrai_faux,reponse_courte',
            'enonce'      => 'required|string',
            'options'     => 'required|array',
            'explication' => 'nullable|string',
            'points'      => 'integer|min:1',
            'ordre'       => 'integer|min:1',
        ]);

        $ordre    = $v['ordre'] ?? LmsQuestion::where('quiz_id', $quizId)->max('ordre') + 1;
        $question = LmsQuestion::create([...$v, 'quiz_id' => $quizId, 'ordre' => $ordre]);
        LmsQuiz::find($quizId)?->increment('nb_questions');

        return response()->json(['success' => true, 'data' => $question], 201);
    }

    public function passerQuiz(Request $request, string $quizId): JsonResponse
    {
        $v = $request->validate([
            'inscription_id'  => 'required|uuid|exists:lms_inscriptions,id',
            'reponses'        => 'required|array',
            'duree_secondes'  => 'integer|min:0',
        ]);

        try {
            $tentative = $this->lms->soumettreTentativeQuiz(
                $quizId,
                auth('api')->id(),
                $v['inscription_id'],
                $v['reponses'],
                $v['duree_secondes'] ?? 0
            );

            return response()->json([
                'success'    => true,
                'data'       => $tentative,
                'message'    => $tentative->reussi ? '✅ Quiz réussi !' : '❌ Quiz non réussi — réessayez',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════
    // INSCRIPTIONS & PROGRESSION
    // ════════════════════════════════════════════════════════

    public function inscrire(Request $request): JsonResponse
    {
        $v = $request->validate([
            'cours_id' => 'required|uuid|exists:lms_cours,id',
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ]);

        $inscription = $this->lms->inscrireEleve($v['cours_id'], $v['eleve_id']);
        return response()->json(['success' => true, 'data' => $inscription, 'message' => 'Inscrit au cours'], 201);
    }

    public function inscriptionEleve(string $eleveId): JsonResponse
    {
        $inscriptions = LmsInscription::where('eleve_id', $eleveId)
            ->with('cours:id,titre,matiere,image_url,nb_lecons,seuil_completion')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['success' => true, 'data' => $inscriptions]);
    }

    public function marquerLecon(Request $request, string $inscriptionId, string $leconId): JsonResponse
    {
        $v = $request->validate(['temps_secondes' => 'integer|min:0']);

        $result = $this->lms->marquerLeconComplete($inscriptionId, $leconId, $v['temps_secondes'] ?? 0);
        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $result['cours_complete']
                ? '🎓 Cours terminé ! Certificat généré.'
                : "Progression : {$result['progression_pct']}%",
        ]);
    }

    public function progressionEleve(string $inscriptionId): JsonResponse
    {
        $inscription = LmsInscription::with([
            'cours.chapitres.lecons',
            'progressions',
        ])->findOrFail($inscriptionId);

        $completees = $inscription->progressions->where('completee', true)->pluck('lecon_id')->toArray();

        $chapitres = $inscription->cours->chapitres->map(fn($ch) => [
            'id'     => $ch->id,
            'titre'  => $ch->titre,
            'lecons' => $ch->lecons->map(fn($l) => [
                'id'        => $l->id,
                'titre'     => $l->titre,
                'type'      => $l->type,
                'completee' => in_array($l->id, $completees),
                'duree'     => $l->duree_minutes,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'inscription'    => $inscription,
                'chapitres'      => $chapitres,
                'progression_pct'=> $inscription->progression_pct,
                'certificat_url' => $inscription->certificat_url,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════
    // DEVOIRS
    // ════════════════════════════════════════════════════════

    public function soumettreDevoir(Request $request, string $leconId): JsonResponse
    {
        $request->validate([
            'inscription_id'    => 'required|uuid|exists:lms_inscriptions,id',
            'fichier'           => 'nullable|file|max:20480', // 20MB
            'commentaire_eleve' => 'nullable|string|max:1000',
        ]);

        $cheminFichier = null;
        $nomFichier    = null;

        if ($request->hasFile('fichier')) {
            $file          = $request->file('fichier');
            $cheminFichier = $file->store("lms/devoirs/{$leconId}", 'public');
            $nomFichier    = $file->getClientOriginalName();
        }

        $devoir = LmsDevoir::updateOrCreate(
            ['lecon_id' => $leconId, 'eleve_id' => auth('api')->id()],
            [
                'inscription_id'    => $request->inscription_id,
                'fichier_url'       => $cheminFichier ? \Illuminate\Support\Facades\Storage::url($cheminFichier) : null,
                'fichier_nom'       => $nomFichier,
                'commentaire_eleve' => $request->commentaire_eleve,
                'statut'            => 'soumis',
                'soumis_le'         => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $devoir, 'message' => 'Devoir soumis'], 201);
    }

    public function corrigerDevoir(Request $request, string $devoirId): JsonResponse
    {
        $v = $request->validate([
            'note'                => 'required|numeric|min:0|max:20',
            'feedback_enseignant' => 'nullable|string|max:1000',
        ]);

        $devoir = LmsDevoir::findOrFail($devoirId);
        $devoir->update([
            ...$v,
            'statut'      => 'corrige',
            'corrige_par' => auth('api')->id(),
            'corrige_le'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $devoir->fresh()]);
    }

    // ════════════════════════════════════════════════════════
    // CERTIFICAT
    // ════════════════════════════════════════════════════════

    public function telechargerCertificat(string $inscriptionId): \Illuminate\Http\Response
    {
        $inscription = LmsInscription::with(['cours.enseignant', 'eleve'])->findOrFail($inscriptionId);

        if (!$inscription->certificat_url) {
            // Générer si pas encore fait
            $url = $this->lms->genererCertificat($inscription);
            $inscription->update(['certificat_url' => $url]);
        }

        $pdf = $this->lms->genererCertificat($inscription);
        $nomFichier = "certificat-{$inscription->eleve->nom}-{$inscription->cours->titre}.pdf";

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lms-certificat', [
            'cours'       => $inscription->cours,
            'eleve'       => $inscription->eleve,
            'inscription' => $inscription,
        ])->setPaper('a4', 'landscape')->download($nomFichier);
    }

    // ════════════════════════════════════════════════════════
    // DASHBOARD
    // ════════════════════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->lms->getDashboard()]);
    }
}
```

---

## ÉTAPE 6 — Routes API

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\LmsController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/lms')->group(function () {
    // Dashboard
    Route::get('/dashboard',                    [LmsController::class, 'dashboard']);

    // Cours
    Route::get('/cours',                        [LmsController::class, 'indexCours']);
    Route::post('/cours',                       [LmsController::class, 'storeCours']);
    Route::get('/cours/{id}',                   [LmsController::class, 'showCours']);
    Route::put('/cours/{id}',                   [LmsController::class, 'updateCours']);
    Route::post('/cours/{id}/publier',          [LmsController::class, 'publierCours']);

    // Chapitres
    Route::post('/cours/{coursId}/chapitres',   [LmsController::class, 'storeChapitre']);
    Route::put('/chapitres/{id}',               [LmsController::class, 'updateChapitre']);
    Route::delete('/chapitres/{id}',            [LmsController::class, 'deleteChapitre']);

    // Leçons
    Route::post('/chapitres/{chapitreId}/lecons',[LmsController::class, 'storeLecon']);
    Route::put('/lecons/{id}',                  [LmsController::class, 'updateLecon']);
    Route::post('/lecons/{id}/upload',          [LmsController::class, 'uploadFichierLecon']);

    // Quiz
    Route::post('/lecons/{leconId}/quiz',       [LmsController::class, 'storeQuiz']);
    Route::post('/quiz/{quizId}/questions',     [LmsController::class, 'storeQuestion']);
    Route::post('/quiz/{quizId}/passer',        [LmsController::class, 'passerQuiz']);

    // Inscriptions & Progression
    Route::post('/inscrire',                    [LmsController::class, 'inscrire']);
    Route::get('/eleve/{eleveId}/inscriptions', [LmsController::class, 'inscriptionEleve']);
    Route::post('/inscription/{id}/lecon/{leconId}/complete', [LmsController::class, 'marquerLecon']);
    Route::get('/inscription/{id}/progression', [LmsController::class, 'progressionEleve']);

    // Devoirs
    Route::post('/lecons/{leconId}/devoir',     [LmsController::class, 'soumettreDevoir']);
    Route::post('/devoirs/{id}/corriger',       [LmsController::class, 'corrigerDevoir']);

    // Certificat
    Route::get('/inscription/{id}/certificat', [LmsController::class, 'telechargerCertificat']);
});
```

---

## ÉTAPE 7 — Page React LmsPage

**Créer :** `edugestdz/frontend/src/pages/LmsPage.jsx`

```jsx
import { useState, useEffect } from 'react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, 'Content-Type':'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
  ...opts,
}).then(r => r.json());

const TYPES = { texte:'📄', video:'🎥', pdf:'📑', audio:'🎵', lien:'🔗', quiz:'✏️', devoir:'📝' };
const NIVEAUX_COULEURS = { '3AS':'#2563EB','2AS':'#7C3AED','1AS':'#10B981','4AM':'#F59E0B','3AM':'#EF4444' };

export default function LmsPage() {
  const [cours, setCours]         = useState([]);
  const [selected, setSelected]   = useState(null);
  const [tab, setTab]             = useState('catalogue');
  const [loading, setLoading]     = useState(true);
  const [stats, setStats]         = useState(null);
  const [showNew, setShowNew]     = useState(false);
  const [form, setForm] = useState({ titre:'', description:'', matiere:'', langue:'ar', niveaux_cibles:[], seuil_completion:80, certificat_actif:true });
  const [saving, setSaving]       = useState(false);
  const [msg, setMsg]             = useState('');

  useEffect(() => { loadCours(); }, []);

  const loadCours = async () => {
    setLoading(true);
    const [coursRes, dashRes] = await Promise.all([
      api('/lms/cours'), api('/lms/dashboard'),
    ]);
    setCours(coursRes?.data?.data ?? []);
    setStats(dashRes?.data);
    setLoading(false);
  };

  const creerCours = async () => {
    setSaving(true);
    const res = await api('/lms/cours', { method:'POST', body:JSON.stringify(form) });
    setSaving(false);
    if (res.success) { setShowNew(false); loadCours(); setMsg('✅ Cours créé'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const publier = async (id) => {
    const res = await api(`/lms/cours/${id}/publier`, { method:'POST' });
    if (res.success) loadCours();
    else alert(res.message);
  };

  const St = ({ label, value, color, icon }) => (
    <div style={{ background:'#0D1117', border:`1px solid #1E2D40`, borderTop:`2px solid ${color}`, borderRadius:'12px', padding:'16px 20px' }}>
      <div style={{ fontSize:'10px', fontWeight:700, color:'#64748B', textTransform:'uppercase', letterSpacing:'1px', marginBottom:'8px' }}>{icon} {label}</div>
      <div style={{ fontSize:'26px', fontWeight:900, color:'#fff' }}>{loading ? '...' : (value ?? 0)}</div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#070B14', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>📚 LMS — Cours en ligne</h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>Vidéos · PDF · Quiz · Devoirs · Certificats</p>
        </div>
        <button onClick={() => setShowNew(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'10px 18px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
          + Créer un cours
        </button>
      </div>

      {msg && <div style={{ background:msg.includes('✅')?'#0d2515':'#450a0a', border:`1px solid ${msg.includes('✅')?'#16a34a':'#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color:msg.includes('✅')?'#4ade80':'#f87171' }}>{msg}</div>}

      {/* Stats */}
      {stats && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'24px' }}>
          <St label="Cours publiés"    value={stats.total_cours}       color="#2563EB" icon="📚" />
          <St label="Élèves inscrits"  value={stats.total_inscrits}    color="#10B981" icon="👦" />
          <St label="Cours complétés"  value={stats.cours_completes}   color="#7C3AED" icon="✅" />
          <St label="Certificats"      value={stats.certificats}       color="#F59E0B" icon="🎓" />
        </div>
      )}

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['catalogue','📚 Catalogue'],['activite','📊 Activité récente']].map(([id,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{ background:tab===id?'#1e3a5f':'#111318', color:tab===id?'#60a5fa':'#64748B', border:`1px solid ${tab===id?'#3b82f6':'#1E2D40'}`, borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>{label}</button>
        ))}
      </div>

      {/* Catalogue */}
      {tab === 'catalogue' && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'14px' }}>
          {loading ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>Chargement...</div>
          : cours.length === 0 ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>Aucun cours. Créez votre premier cours LMS.</div>
          : cours.map(c => (
            <div key={c.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
              {/* Vignette */}
              <div style={{ height:'120px', background:'linear-gradient(135deg,#1e3a5f,#2563eb33)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'48px' }}>
                {c.matiere?.includes('Maths') ? '📐' : c.matiere?.includes('Phys') ? '⚗️' : c.matiere?.includes('Arabe') ? '📖' : '📚'}
              </div>
              <div style={{ padding:'14px' }}>
                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'6px' }}>
                  <div style={{ fontWeight:800, fontSize:'13px', color:'#fff', flex:1 }}>{c.titre}</div>
                  <span style={{ background:c.publie?'#10B98122':'#64748b22', color:c.publie?'#10B981':'#94A3B8', fontSize:'9px', fontWeight:700, padding:'2px 8px', borderRadius:'20px', flexShrink:0 }}>
                    {c.publie ? '✅ Publié' : '⚪ Brouillon'}
                  </span>
                </div>
                {c.matiere && <div style={{ fontSize:'11px', color:'#64748B', marginBottom:'6px' }}>📚 {c.matiere}</div>}
                <div style={{ display:'flex', gap:'6px', flexWrap:'wrap', marginBottom:'10px' }}>
                  {(c.niveaux_cibles || []).map(n => (
                    <span key={n} style={{ background:(NIVEAUX_COULEURS[n]||'#64748b')+'22', color:NIVEAUX_COULEURS[n]||'#94A3B8', fontSize:'9px', fontWeight:700, padding:'1px 7px', borderRadius:'20px' }}>{n}</span>
                  ))}
                </div>
                <div style={{ display:'flex', justifyContent:'space-between', fontSize:'10px', color:'#64748B', marginBottom:'10px' }}>
                  <span>👦 {c.inscriptions_count ?? 0} inscrits</span>
                  <span>📖 {c.nb_lecons ?? 0} leçons</span>
                  <span>⏱ {c.duree_estimee || '—'}</span>
                </div>
                <div style={{ display:'flex', gap:'6px' }}>
                  <button onClick={() => { setSelected(c); setTab('cours-detail'); }} style={{ flex:2, background:'#2563EB', color:'#fff', border:'none', borderRadius:'8px', padding:'7px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    Gérer le cours
                  </button>
                  <button onClick={() => publier(c.id)} style={{ flex:1, background:c.publie?'#64748b22':'#10B98122', color:c.publie?'#94A3B8':'#10B981', border:`1px solid ${c.publie?'#64748b44':'#10B98144'}`, borderRadius:'8px', padding:'7px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                    {c.publie ? 'Dépublier' : 'Publier'}
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Activité récente */}
      {tab === 'activite' && (
        <div style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
          <div style={{ padding:'14px 20px', borderBottom:'1px solid #1E2D40', fontSize:'13px', fontWeight:700, color:'#fff' }}>
            📊 Activité récente des élèves
          </div>
          {(stats?.activite_recente || []).length === 0 ? (
            <div style={{ padding:'40px', textAlign:'center', color:'#64748B' }}>Aucune activité enregistrée.</div>
          ) : (stats?.activite_recente || []).map((p, i) => (
            <div key={i} style={{ display:'flex', alignItems:'center', gap:'12px', padding:'12px 20px', borderBottom:'1px solid #1E2D4044' }}>
              <span style={{ fontSize:'18px' }}>👦</span>
              <div style={{ flex:1 }}>
                <div style={{ fontSize:'12px', fontWeight:700, color:'#E2E8F0' }}>{p.eleve?.prenom} {p.eleve?.nom}</div>
                <div style={{ fontSize:'10px', color:'#64748B' }}>a complété : {p.lecon?.titre}</div>
              </div>
              <span style={{ fontSize:'10px', color:p.completee?'#10B981':'#64748B', fontWeight:700 }}>
                {p.completee ? '✅ Complété' : '⏳ En cours'}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* Modal créer cours */}
      {showNew && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowNew(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'520px', maxWidth:'90%' }} onClick={e=>e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📚 Nouveau cours LMS</h3>
            {[
              ['Titre *', 'titre', 'text', 'ex: Cours de Mathématiques 3AS'],
              ['Matière', 'matiere', 'text', 'ex: Mathématiques'],
              ['Durée estimée', 'duree_estimee', 'text', 'ex: 3h30'],
            ].map(([label, key, type, ph]) => (
              <div key={key} style={{ marginBottom:'10px' }}>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                <input type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))} placeholder={ph}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            ))}
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'10px' }}>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Langue</label>
                <select value={form.langue} onChange={e=>setForm(f=>({...f,langue:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                  <option value="ar">العربية</option>
                  <option value="fr">Français</option>
                  <option value="en">English</option>
                </select>
              </div>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Seuil certificat (%)</label>
                <input type="number" value={form.seuil_completion} min="1" max="100"
                  onChange={e=>setForm(f=>({...f,seuil_completion:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            </div>
            <div style={{ marginBottom:'14px', fontSize:'11px', color:'#64748B', background:'#1E293B', borderRadius:'8px', padding:'10px' }}>
              📖 Après la création → ajouter des chapitres, des leçons, des quiz et des devoirs.
            </div>
            <div style={{ display:'flex', gap:'10px' }}>
              <button onClick={() => setShowNew(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={creerCours} disabled={saving || !form.titre} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Création...' : '✅ Créer le cours'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 8 — Ajouter dans App.jsx et Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/App.jsx`

```jsx
import LmsPage from '@pages/LmsPage';
<Route path="lms" element={<LmsPage />} />
```

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Dans la section "Pédagogie" :
```jsx
{ label: 'LMS — Cours en ligne', path: '/lms', icon: '🖥️' },
```

---

## ÉTAPE 9 — Tests

**Créer :**
`edugestdz/backend/tests/Feature/Controllers/LmsControllerTest.php`

```php
<?php
namespace Tests\Feature\Controllers;
use App\Models\User;
use App\Models\Eleve;
use App\Models\LmsCours;
use App\Models\LmsChapitre;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsInscription;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class LmsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enseignant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin      = User::factory()->create(['role' => 'admin']);
        $this->enseignant = User::factory()->create(['role' => 'enseignant']);
    }

    private function makeCours(array $attrs = []): LmsCours
    {
        return LmsCours::create(array_merge([
            'tenant_id'       => Str::uuid(),
            'enseignant_id'   => $this->enseignant->id,
            'titre'           => 'Cours Maths 3AS',
            'matiere'         => 'Mathématiques',
            'niveaux_cibles'  => ['3AS'],
            'langue'          => 'ar',
            'publie'          => true,
            'certificat_actif'=> true,
            'seuil_completion'=> 80,
        ], $attrs));
    }

    public function test_dashboard_lms(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/lms/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success','data' => ['total_cours','total_inscrits','cours_completes','certificats']]);
    }

    public function test_lister_cours(): void
    {
        $this->makeCours();
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/lms/cours')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_creer_cours(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/lms/cours', [
                'titre'           => 'Physique Chimie 2AS',
                'matiere'         => 'Physique',
                'niveaux_cibles'  => ['2AS'],
                'langue'          => 'fr',
                'seuil_completion'=> 75,
                'certificat_actif'=> true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.titre', 'Physique Chimie 2AS')
            ->assertJsonPath('data.publie', false);
    }

    public function test_cours_cree_en_brouillon(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/lms/cours', ['titre' => 'Test', 'langue' => 'ar'])
            ->assertStatus(201)
            ->assertJsonPath('data.publie', false);
    }

    public function test_creer_chapitre(): void
    {
        $cours = $this->makeCours();
        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/cours/{$cours->id}/chapitres", [
                'titre' => 'Chapitre 1 : Introduction',
                'ordre' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.titre', 'Chapitre 1 : Introduction');
    }

    public function test_creer_lecon_texte(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id'=>$cours->id,'titre'=>'Ch1','ordre'=>1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/chapitres/{$chapitre->id}/lecons", [
                'titre'   => 'Leçon 1 : Les équations',
                'type'    => 'texte',
                'contenu' => '<p>Contenu de la leçon</p>',
                'ordre'   => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'texte');
    }

    public function test_creer_lecon_video(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id'=>$cours->id,'titre'=>'Ch1','ordre'=>1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/chapitres/{$chapitre->id}/lecons", [
                'titre'         => 'Vidéo : Résoudre une équation',
                'type'          => 'video',
                'ressource_url' => 'https://www.youtube.com/embed/xxx',
                'duree_minutes' => 12,
                'ordre'         => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'video');
    }

    public function test_publier_cours_sans_lecon_echoue(): void
    {
        $cours = $this->makeCours(['publie' => false]);
        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/cours/{$cours->id}/publier")
            ->assertStatus(422);
    }

    public function test_inscrire_eleve(): void
    {
        $cours = $this->makeCours();
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', [
                'cours_id' => $cours->id,
                'eleve_id' => $eleve->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.cours_id', $cours->id);
    }

    public function test_double_inscription_retourne_meme_record(): void
    {
        $cours = $this->makeCours();
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', ['cours_id'=>$cours->id,'eleve_id'=>$eleve->id])
            ->assertStatus(201);

        // Deuxième inscription → même résultat (pas de doublon)
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', ['cours_id'=>$cours->id,'eleve_id'=>$eleve->id])
            ->assertStatus(201);

        $this->assertEquals(1, LmsInscription::where('cours_id', $cours->id)->where('eleve_id', $eleve->id)->count());
    }

    public function test_quiz_correction_automatique(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id'=>$cours->id,'titre'=>'Ch1','ordre'=>1]);
        $eleve    = Eleve::factory()->create();
        $insc     = LmsInscription::create(['cours_id'=>$cours->id,'eleve_id'=>$eleve->id,'tenant_id'=>$cours->tenant_id]);
        $lecon    = LmsLecon::create(['chapitre_id'=>$chapitre->id,'titre'=>'Quiz','type'=>'quiz','ordre'=>1]);
        $quiz     = LmsQuiz::create(['lecon_id'=>$lecon->id,'titre'=>'Quiz Test','seuil_reussite'=>60,'nb_tentatives_max'=>3]);
        $question = LmsQuestion::create([
            'quiz_id' => $quiz->id,
            'type'    => 'qcm',
            'enonce'  => 'Combien font 2+2 ?',
            'options' => [['id'=>'a','texte'=>'3','correct'=>false],['id'=>'b','texte'=>'4','correct'=>true]],
            'points'  => 1,
            'ordre'   => 1,
        ]);
        $quiz->update(['nb_questions' => 1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/quiz/{$quiz->id}/passer", [
                'inscription_id' => $insc->id,
                'reponses'       => [$question->id => 'b'], // bonne réponse
                'duree_secondes' => 30,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.reussi', true)
            ->assertJsonPath('data.pourcentage', 100);
    }

    public function test_marquer_lecon_complete(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id'=>$cours->id,'titre'=>'Ch1','ordre'=>1]);
        $eleve    = Eleve::factory()->create();
        $insc     = LmsInscription::create(['cours_id'=>$cours->id,'eleve_id'=>$eleve->id,'tenant_id'=>$cours->tenant_id]);
        $lecon    = LmsLecon::create(['chapitre_id'=>$chapitre->id,'titre'=>'L1','type'=>'texte','ordre'=>1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/inscription/{$insc->id}/lecon/{$lecon->id}/complete", ['temps_secondes'=>120])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['progression_pct','cours_complete']]);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/lms/cours')->assertStatus(401);
        $this->getJson('/api/v1/lms/dashboard')->assertStatus(401);
    }
}
```

---

## ÉTAPE 10 — Exécution

```bash
cd edugestdz/backend

# Migration
php artisan migrate

# Autoload
composer dump-autoload -o

# Tests
php artisan test --parallel
# → 0 régression + 13 nouveaux tests verts

# Commit
git add .
git commit -m "feat: Module LMS — Cours en ligne · Chapitres · Leçons (vidéo/PDF/quiz/devoir) · Quiz correction auto · Progression élève · Certificats PDF · 13 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_LMS_MODULE.md — 10 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. Toutes les tables LMS sont préfixées lms_ pour éviter tout conflit
   avec les tables existantes (cours, evaluations, notes, etc.).
4. LmsService injecte ParentNotificationService — si ce service n'existe pas,
   remplacer par FirebaseService.notifyParentsEleve() dans le constructeur.
5. La vue PDF lms-certificat.blade.php va dans resources/views/pdf/
   (créer le dossier s'il n'existe pas).
6. Les 8 tables LMS doivent être dans UNE SEULE migration.
7. Storage::disk('public') doit être lié (php artisan storage:link) —
   ajouter cette commande dans start.sh si self-hosted.
8. Ne pas modifier CoursController, EvaluationController,
   ni aucun contrôleur existant.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
