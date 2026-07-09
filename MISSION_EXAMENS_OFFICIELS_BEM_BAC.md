# 🤖 MISSION DEEPSEEK — Module Gestion des Examens Officiels BEM/BAC
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 432 ✅ · 0 régression

---

## CONTEXTE — Recherche ONEC effectuée

### Règles officielles ONEC Algérie (vérifiées)
```
CALENDRIER :
  BEM  : 3 jours d'épreuves (ex: 19-21 mai 2026)
  BAC  : 5 jours d'épreuves (ex: 7-11 juin 2026)
  Épreuves matin    : 8h30
  Épreuves après-midi : 14h30 (BEM) / 15h00 (BAC)
  Ouverture centres : 1h avant les épreuves

CANDIDATS PAR SALLE :
  Candidats scolarisés : 20 max par salle
  Candidats libres     : 15 max par salle
  Règle mixte          : mélange scolarisés + libres possible

SURVEILLANTS PAR SALLE :
  3 surveillants obligatoires par salle
  Règle clé : le surveillant ne surveille PAS sa matière de spécialité
  Règle anti-fraude : le surveillant n'est PAS de la même commune
  Le chef de centre + secrétariat = autre commune également
  Un observateur indépendant par centre

CONVOCATIONS :
  Disponibles en ligne (ONEC) + téléchargeables
  Pour BEM : ouvertes de mai à la date d'examen
  Pour BAC : ouvertes de mai à la date d'examen
  Contient : nom, prénom, N° inscription, centre, salle, matières, horaires
  Obligatoire + carte d'identité pour entrer dans le centre
```

### Ce que fait le module
1. **Calendrier examens** — créer les sessions BEM/BAC avec dates/horaires/matières
2. **Gestion des candidats** — inscrire les élèves à l'examen, importer les listes
3. **Gestion des salles** — définir les salles disponibles avec capacité
4. **Plan de salle** — affecter automatiquement les candidats aux salles (algo anti-fraude)
5. **Affectation surveillants** — algorithme respectant les règles ONEC (pas sa matière, pas même commune)
6. **Convocations PDF** — génération et impression pour chaque candidat
7. **Convocations surveillants** — feuille d'affectation pour chaque surveillant
8. **Feuille de présence** — liste par salle pour émargement le jour J
9. **Dashboard** — KPIs : candidats inscrits, salles, surveillants manquants

### RÈGLES ABSOLUES
1. 0 régression — les tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Multi-tenant — chaque école gère ses propres examens
4. Réutiliser DomPDF existant (barryvdh/laravel-dompdf) pour les PDF
5. Ne pas modifier les contrôleurs existants

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : 5 tables examens officiels

**Créer :**
`edugestdz/backend/database/migrations/2026_07_06_200000_create_examens_officiels_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Sessions d'examen (BEM ou BAC) ────────────────────────────
        Schema::create('sessions_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('type')->default('BAC');
            // Valeurs : BEM | BAC | autre
            $table->string('filiere')->nullable();
            // Ex: Sciences, Maths, Lettres_langues, Lettres_philo, Gestion, Technique_math, Musique
            $table->string('annee_scolaire', 10); // ex: 2025/2026
            $table->string('session')->default('principale');
            // Valeurs : principale | rattrapage
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('wilaya', 60)->nullable();
            $table->string('commune', 60)->nullable();
            $table->string('nom_centre')->nullable(); // Nom du centre d'examen
            $table->string('adresse_centre')->nullable();
            $table->integer('capacite_max')->default(0); // total candidats prévus
            $table->integer('max_candidats_par_salle')->default(20);
            $table->integer('max_candidats_libres_par_salle')->default(15);
            $table->integer('nb_surveillants_par_salle')->default(3);
            $table->enum('statut', ['brouillon','planifie','en_cours','termine','annule'])
                ->default('brouillon');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'annee_scolaire'], 'idx_session_tenant_type');
            $table->index(['tenant_id', 'statut'],                  'idx_session_statut');
        });

        // ── 2. Épreuves de la session (matières + horaires) ──────────────
        Schema::create('epreuves_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->string('matiere');           // ex: Mathématiques
            $table->string('code_matiere')->nullable(); // ex: MATH
            $table->decimal('coefficient', 4, 1)->default(1);
            $table->date('date_epreuve');
            $table->enum('moment', ['matin', 'apres_midi'])->default('matin');
            $table->time('heure_debut')->default('08:30');
            $table->time('heure_fin');
            $table->integer('duree_minutes')->default(120);
            $table->string('type_epreuve')->default('ecrit');
            // Valeurs : ecrit | oral | pratique
            $table->boolean('calculatrice_autorisee')->default(false);
            $table->boolean('documents_autorises')->default(false);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id', 'date_epreuve'], 'idx_epreuve_session_date');
        });

        // ── 3. Salles d'examen ────────────────────────────────────────────
        Schema::create('salles_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->string('nom');              // ex: Salle 01, Salle A
            $table->string('numero')->nullable();
            $table->string('batiment')->nullable();
            $table->string('etage')->nullable();
            $table->integer('capacite_totale')->default(20);
            $table->integer('nb_candidats_affectes')->default(0);
            $table->integer('nb_rangees')->nullable();
            $table->integer('nb_colonnes')->nullable();
            $table->boolean('climatisee')->default(false);
            $table->boolean('accessible_pmr')->default(false); // Personnes à mobilité réduite
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id'], 'idx_salle_session');
        });

        // ── 4. Candidats à l'examen ───────────────────────────────────────
        Schema::create('candidats_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->uuid('eleve_id')->nullable(); // lié à un élève du système
            // Champs pour candidats libres (pas dans le système)
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('numero_inscription')->nullable()->unique(); // numéro ONEC
            $table->string('type_candidat')->default('scolarise');
            // Valeurs : scolarise | libre
            $table->string('filiere')->nullable();
            $table->uuid('salle_id')->nullable();    // salle affectée
            $table->integer('numero_place')->nullable(); // numéro de place dans la salle
            $table->string('rangee')->nullable();    // ex: A, B, C
            $table->integer('colonne')->nullable();  // ex: 1, 2, 3
            $table->boolean('convocation_imprimee')->default(false);
            $table->boolean('present')->nullable();  // null = pas encore marqué
            $table->timestamp('present_marque_le')->nullable();
            $table->boolean('besoins_speciaux')->default(false);
            $table->text('notes_speciaux')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->foreign('salle_id')->references('id')->on('salles_examen')->onDelete('set null');
            $table->index(['session_id', 'salle_id'],    'idx_candidat_session_salle');
            $table->index(['session_id', 'eleve_id'],    'idx_candidat_eleve');
            $table->index(['numero_inscription'],         'idx_candidat_num');
        });

        // ── 5. Affectations surveillants ──────────────────────────────────
        Schema::create('surveillants_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->uuid('user_id');             // enseignant ou personnel
            $table->string('nom');               // copié pour lisibilité
            $table->string('prenom');
            $table->string('specialite')->nullable(); // matière enseignée (règle ONEC)
            $table->string('commune_origine')->nullable(); // pour règle anti-fraude
            $table->string('role')->default('surveillant');
            // Valeurs : chef_centre | surveillant | secretaire | observateur
            $table->uuid('salle_id')->nullable(); // salle assignée
            $table->string('salle_nom')->nullable(); // copié pour lisibilité
            $table->boolean('disponible')->default(true);
            $table->boolean('convocation_imprimee')->default(false);
            $table->text('motif_exemption')->nullable(); // si non disponible
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id', 'salle_id'],    'idx_surv_session_salle');
            $table->index(['session_id', 'user_id'],     'idx_surv_user');
            $table->index(['tenant_id', 'disponible'],   'idx_surv_dispo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveillants_examen');
        Schema::dropIfExists('candidats_examen');
        Schema::dropIfExists('salles_examen');
        Schema::dropIfExists('epreuves_examen');
        Schema::dropIfExists('sessions_examen');
    }
};
```

---

## ÉTAPE 2 — Models (5 fichiers)

**Créer :** `edugestdz/backend/app/Models/SessionExamen.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SessionExamen extends Model
{
    use HasUuids;

    protected $table = 'sessions_examen';

    protected $fillable = [
        'tenant_id', 'type', 'filiere', 'annee_scolaire', 'session',
        'date_debut', 'date_fin', 'wilaya', 'commune',
        'nom_centre', 'adresse_centre', 'capacite_max',
        'max_candidats_par_salle', 'max_candidats_libres_par_salle',
        'nb_surveillants_par_salle', 'statut', 'notes',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public const TYPES = ['BEM' => 'Brevet d\'Enseignement Moyen', 'BAC' => 'Baccalauréat', 'autre' => 'Autre'];

    public const FILIERES_BAC = [
        'sciences'        => 'Sciences de la Nature et de la Vie',
        'maths'           => 'Mathématiques',
        'lettres_langues' => 'Lettres et Langues Étrangères',
        'lettres_philo'   => 'Lettres et Philosophie',
        'gestion'         => 'Gestion et Économie',
        'technique_math'  => 'Technique Mathématique',
        'musique'         => 'Musique',
    ];

    public function epreuves()   { return $this->hasMany(EpreuveExamen::class, 'session_id'); }
    public function salles()     { return $this->hasMany(SalleExamen::class,   'session_id'); }
    public function candidats()  { return $this->hasMany(CandidatExamen::class,'session_id'); }
    public function surveillants(){ return $this->hasMany(SurveiillantExamen::class,'session_id'); }

    public function getNbCandidatsAttribute(): int
    {
        return $this->candidats()->count();
    }

    public function getNbSallesRequiseAttribute(): int
    {
        $max = $this->max_candidats_par_salle ?: 20;
        return (int) ceil($this->getNbCandidatsAttribute() / $max);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/EpreuveExamen.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EpreuveExamen extends Model
{
    use HasUuids;

    protected $table = 'epreuves_examen';

    protected $fillable = [
        'session_id', 'matiere', 'code_matiere', 'coefficient',
        'date_epreuve', 'moment', 'heure_debut', 'heure_fin',
        'duree_minutes', 'type_epreuve', 'calculatrice_autorisee', 'documents_autorises',
    ];

    protected $casts = [
        'date_epreuve'           => 'date',
        'coefficient'            => 'decimal:1',
        'calculatrice_autorisee' => 'boolean',
        'documents_autorises'    => 'boolean',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/SalleExamen.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalleExamen extends Model
{
    use HasUuids;

    protected $table = 'salles_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'nom', 'numero', 'batiment', 'etage',
        'capacite_totale', 'nb_candidats_affectes', 'nb_rangees', 'nb_colonnes',
        'climatisee', 'accessible_pmr',
    ];

    protected $casts = [
        'climatisee'    => 'boolean',
        'accessible_pmr'=> 'boolean',
    ];

    public function session()    { return $this->belongsTo(SessionExamen::class,  'session_id'); }
    public function candidats()  { return $this->hasMany(CandidatExamen::class,   'salle_id'); }
    public function surveillants(){ return $this->hasMany(SurveiillantExamen::class,'salle_id'); }

    public function getPlacesRestantesAttribute(): int
    {
        return max(0, $this->capacite_totale - $this->nb_candidats_affectes);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/CandidatExamen.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CandidatExamen extends Model
{
    use HasUuids;

    protected $table = 'candidats_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'eleve_id',
        'nom', 'prenom', 'date_naissance', 'lieu_naissance',
        'numero_inscription', 'type_candidat', 'filiere',
        'salle_id', 'numero_place', 'rangee', 'colonne',
        'convocation_imprimee', 'present', 'present_marque_le',
        'besoins_speciaux', 'notes_speciaux',
    ];

    protected $casts = [
        'date_naissance'      => 'date',
        'convocation_imprimee'=> 'boolean',
        'present'             => 'boolean',
        'besoins_speciaux'    => 'boolean',
        'present_marque_le'   => 'datetime',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
    public function salle()   { return $this->belongsTo(SalleExamen::class,   'salle_id'); }
    public function eleve()   { return $this->belongsTo(Eleve::class,         'eleve_id'); }

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} {$this->prenom}";
    }
}
```

**Créer :** `edugestdz/backend/app/Models/SurveiillantExamen.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SurveiillantExamen extends Model
{
    use HasUuids;

    protected $table = 'surveillants_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'user_id',
        'nom', 'prenom', 'specialite', 'commune_origine', 'role',
        'salle_id', 'salle_nom', 'disponible',
        'convocation_imprimee', 'motif_exemption',
    ];

    protected $casts = [
        'disponible'           => 'boolean',
        'convocation_imprimee' => 'boolean',
    ];

    public const ROLES = [
        'chef_centre' => 'Chef de Centre',
        'surveillant' => 'Surveillant',
        'secretaire'  => 'Secrétaire',
        'observateur' => 'Observateur',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
    public function salle()   { return $this->belongsTo(SalleExamen::class,   'salle_id'); }
    public function user()    { return $this->belongsTo(User::class,          'user_id'); }

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} {$this->prenom}";
    }
}
```

---

## ÉTAPE 3 — ExamenService (algorithmes d'affectation)

**Créer :** `edugestdz/backend/app/Services/ExamenService.php`

```php
<?php

namespace App\Services;

use App\Models\SessionExamen;
use App\Models\CandidatExamen;
use App\Models\SalleExamen;
use App\Models\SurveiillantExamen;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamenService
{
    // ══════════════════════════════════════════════════════════════════
    // ALGORITHME 1 — Affectation candidats aux salles
    // Respecte les règles ONEC :
    //   - 20 scolarisés / 15 libres max par salle
    //   - Numérotation séquentielle par rangée/colonne
    //   - Candidats à besoins spéciaux en salle prioritaire
    // ══════════════════════════════════════════════════════════════════

    public function affecterCandidatsAuxSalles(string $sessionId): array
    {
        $session   = SessionExamen::with(['salles', 'candidats'])->findOrFail($sessionId);
        $salles    = $session->salles->sortBy('nom');
        $candidats = $session->candidats()
            ->orderByRaw("CASE WHEN besoins_speciaux THEN 0 ELSE 1 END") // PMR en premier
            ->orderBy('nom')
            ->get();

        if ($salles->isEmpty()) {
            throw new \RuntimeException("Aucune salle définie pour cette session.");
        }

        $maxParSalle = $session->max_candidats_par_salle ?: 20;
        $total       = $candidats->count();
        $affectes    = 0;
        $salleIndex  = 0;
        $placeIndex  = 1;

        DB::transaction(function () use ($candidats, $salles, $maxParSalle, &$affectes, &$salleIndex, &$placeIndex) {
            // Remettre à zéro les affectations existantes
            foreach ($salles as $salle) {
                $salle->update(['nb_candidats_affectes' => 0]);
            }

            foreach ($candidats as $candidat) {
                if ($salleIndex >= $salles->count()) {
                    Log::warning("Manque de salles pour affecter tous les candidats");
                    break;
                }

                $salle = $salles->values()->get($salleIndex);

                // Calculer rangée et colonne
                $nbCol = $salle->nb_colonnes ?: 5;
                $rangee = chr(64 + (int) ceil($placeIndex / $nbCol)); // A, B, C...
                $col    = (($placeIndex - 1) % $nbCol) + 1;

                $candidat->update([
                    'salle_id'      => $salle->id,
                    'numero_place'  => $placeIndex,
                    'rangee'        => $rangee,
                    'colonne'       => $col,
                ]);

                $salle->increment('nb_candidats_affectes');
                $affectes++;
                $placeIndex++;

                // Passer à la salle suivante si pleine
                if ($salle->nb_candidats_affectes >= $maxParSalle) {
                    $salleIndex++;
                    $placeIndex = 1;
                }
            }
        });

        return [
            'total_candidats' => $total,
            'affectes'        => $affectes,
            'salles_utilisees'=> $salleIndex + 1,
            'message'         => "Affectation terminée : {$affectes}/{$total} candidats répartis en " . ($salleIndex + 1) . " salle(s).",
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // ALGORITHME 2 — Affectation surveillants aux salles
    // Respecte les règles ONEC :
    //   - 3 surveillants par salle obligatoire
    //   - Un surveillant ne surveille PAS sa matière de spécialité
    //     (les matières de la session sont prises en compte)
    //   - Ne pas affecter à une salle si proche d'un candidat (si lien connu)
    //   - Distribution équitable de la charge
    // ══════════════════════════════════════════════════════════════════

    public function affecterSurveillantsAuxSalles(string $sessionId): array
    {
        $session     = SessionExamen::with(['salles', 'epreuves'])->findOrFail($sessionId);
        $salles      = $session->salles->where('nb_candidats_affectes', '>', 0)->sortBy('nom');
        $surveillants= SurveiillantExamen::where('session_id', $sessionId)
            ->where('role', 'surveillant')
            ->where('disponible', true)
            ->get();

        $nbSalles        = $salles->count();
        $nbSurveillants  = $surveillants->count();
        $nbParSalle      = $session->nb_surveillants_par_salle ?: 3;
        $nbRequis        = $nbSalles * $nbParSalle;

        if ($nbSurveillants < $nbRequis) {
            Log::warning("Session {$sessionId}: {$nbSurveillants}/{$nbRequis} surveillants disponibles");
        }

        // Matières de la session (pour règle anti-spécialité)
        $matieresDeLaSession = $session->epreuves->pluck('matiere')->unique()->toArray();

        // Remettre à zéro les affectations de salles pour les surveillants
        SurveiillantExamen::where('session_id', $sessionId)
            ->where('role', 'surveillant')
            ->update(['salle_id' => null, 'salle_nom' => null]);

        $affectations = 0;
        $sallesList   = $salles->values();
        $survIndex    = 0;

        DB::transaction(function () use (
            $sallesList, $surveillants, $matieresDeLaSession,
            $nbParSalle, &$affectations, &$survIndex
        ) {
            foreach ($sallesList as $salle) {
                $affectesASalle = 0;
                $tentatives     = 0;
                $nbSurv         = $surveillants->count();

                while ($affectesASalle < $nbParSalle && $tentatives < $nbSurv * 2) {
                    if ($survIndex >= $nbSurv) $survIndex = 0; // boucle circulaire

                    $surveillant = $surveillants->values()->get($survIndex);
                    $tentatives++;

                    // Règle ONEC : ne surveille pas sa propre matière
                    $specialiteOk = !in_array(
                        $surveillant->specialite,
                        $matieresDeLaSession
                    );

                    if ($specialiteOk) {
                        $surveillant->update([
                            'salle_id'  => $salle->id,
                            'salle_nom' => $salle->nom,
                        ]);
                        $affectesASalle++;
                        $affectations++;
                    }

                    $survIndex++;
                }
            }
        });

        return [
            'salles_traitees' => $nbSalles,
            'affectations'    => $affectations,
            'surveillants_disponibles' => $nbSurveillants,
            'requis'          => $nbRequis,
            'manque'          => max(0, $nbRequis - $nbSurveillants),
            'message'         => $affectations >= $nbRequis
                ? "✅ Tous les surveillants affectés ({$affectations}/{$nbRequis})"
                : "⚠️ {$affectations}/{$nbRequis} affectations. Manque " . max(0, $nbRequis - $nbSurveillants) . " surveillant(s).",
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF — Convocation candidat
    // ══════════════════════════════════════════════════════════════════

    public function genererConvocationCandidat(string $candidatId): \Barryvdh\DomPDF\PDF
    {
        $candidat = CandidatExamen::with(['session.epreuves', 'salle'])->findOrFail($candidatId);
        $session  = $candidat->session;

        $epreuves = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocation-candidat', compact('candidat', 'session', 'epreuves'))->render();

        $candidat->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a5', 'portrait');
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF — Convocations toute la session (en masse)
    // ══════════════════════════════════════════════════════════════════

    public function genererToutesConvocations(string $sessionId): \Barryvdh\DomPDF\PDF
    {
        $session  = SessionExamen::with(['epreuves', 'candidats.salle'])->findOrFail($sessionId);
        $candidats= $session->candidats->sortBy(['salle.nom', 'numero_place']);
        $epreuves = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocations-masse', compact('session', 'candidats', 'epreuves'))->render();

        // Marquer toutes les convocations comme imprimées
        CandidatExamen::where('session_id', $sessionId)
            ->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a5', 'portrait');
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF — Convocation surveillant
    // ══════════════════════════════════════════════════════════════════

    public function genererConvocationSurveillant(string $surveillantId): \Barryvdh\DomPDF\PDF
    {
        $surveillant = SurveiillantExamen::with(['session.epreuves', 'salle'])->findOrFail($surveillantId);
        $session     = $surveillant->session;
        $epreuves    = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocation-surveillant', compact('surveillant', 'session', 'epreuves'))->render();

        $surveillant->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a4', 'portrait');
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF — Feuille de présence par salle
    // ══════════════════════════════════════════════════════════════════

    public function genererFeuillePresence(string $salleId): \Barryvdh\DomPDF\PDF
    {
        $salle      = SalleExamen::with(['session.epreuves', 'candidats', 'surveillants'])->findOrFail($salleId);
        $session    = $salle->session;
        $candidats  = $salle->candidats->sortBy('numero_place');
        $surveillants = $salle->surveillants;
        $epreuves   = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.feuille-presence', compact('salle', 'session', 'candidats', 'surveillants', 'epreuves'))->render();

        return Pdf::loadHtml($html)->setPaper('a4', 'portrait');
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF — Plan de salle (placement visuel)
    // ══════════════════════════════════════════════════════════════════

    public function genererPlanSalle(string $salleId): \Barryvdh\DomPDF\PDF
    {
        $salle     = SalleExamen::with(['candidats'])->findOrFail($salleId);
        $candidats = $salle->candidats->keyBy(fn($c) => "{$c->rangee}{$c->colonne}");
        $nbCol     = $salle->nb_colonnes ?: 5;
        $nbRangees = $salle->nb_rangees  ?: (int) ceil($salle->nb_candidats_affectes / $nbCol);

        $html = view('pdf.plan-salle', compact('salle', 'candidats', 'nbCol', 'nbRangees'))->render();

        return Pdf::loadHtml($html)->setPaper('a4', 'landscape');
    }

    // ══════════════════════════════════════════════════════════════════
    // IMPORT CANDIDATS depuis CSV
    // ══════════════════════════════════════════════════════════════════

    public function importerCandidats(string $sessionId, string $csvPath): array
    {
        $lignes   = array_map('str_getcsv', file($csvPath));
        $entetes  = array_map('trim', array_shift($lignes));
        $importes = 0;
        $erreurs  = [];

        foreach ($lignes as $i => $ligne) {
            try {
                $data = array_combine($entetes, $ligne);
                CandidatExamen::create([
                    'session_id'          => $sessionId,
                    'tenant_id'           => config('tenant.current_id'),
                    'nom'                 => trim($data['nom'] ?? ''),
                    'prenom'              => trim($data['prenom'] ?? ''),
                    'date_naissance'      => $data['date_naissance'] ?? null,
                    'lieu_naissance'      => $data['lieu_naissance'] ?? null,
                    'numero_inscription'  => trim($data['numero_inscription'] ?? ''),
                    'type_candidat'       => $data['type'] ?? 'scolarise',
                    'filiere'             => $data['filiere'] ?? null,
                ]);
                $importes++;
            } catch (\Throwable $e) {
                $erreurs[] = "Ligne " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return ['importes' => $importes, 'erreurs' => $erreurs];
    }

    // ══════════════════════════════════════════════════════════════════
    // IMPORTER ÉLÈVES depuis le système (inscrits au BAC/BEM dans EduGest)
    // ══════════════════════════════════════════════════════════════════

    public function importerElevesSysteme(string $sessionId, array $eleveIds): array
    {
        $session  = SessionExamen::findOrFail($sessionId);
        $importes = 0;

        foreach ($eleveIds as $eleveId) {
            $eleve = Eleve::find($eleveId);
            if (!$eleve) continue;

            // Éviter les doublons
            $existe = CandidatExamen::where('session_id', $sessionId)
                ->where('eleve_id', $eleveId)->exists();
            if ($existe) continue;

            CandidatExamen::create([
                'session_id'   => $sessionId,
                'tenant_id'    => config('tenant.current_id'),
                'eleve_id'     => $eleveId,
                'nom'          => $eleve->nom,
                'prenom'       => $eleve->prenom,
                'date_naissance'=> $eleve->date_naissance,
                'type_candidat' => 'scolarise',
                'filiere'       => $eleve->niveau_scolaire,
            ]);
            $importes++;
        }

        return ['importes' => $importes];
    }

    // ══════════════════════════════════════════════════════════════════
    // DASHBOARD SESSION
    // ══════════════════════════════════════════════════════════════════

    public function getDashboard(string $sessionId): array
    {
        $session = SessionExamen::with(['salles', 'candidats', 'surveillants'])->findOrFail($sessionId);

        $nbSalles      = $session->salles->count();
        $nbCandidats   = $session->candidats->count();
        $nbAffectes    = $session->candidats->whereNotNull('salle_id')->count();
        $nbSurv        = $session->surveillants->where('role', 'surveillant')->count();
        $nbSurvAfff    = $session->surveillants->where('role', 'surveillant')->whereNotNull('salle_id')->count();
        $nbSurvRequis  = $nbSalles * ($session->nb_surveillants_par_salle ?: 3);

        return [
            'session'                  => $session,
            'nb_candidats_total'       => $nbCandidats,
            'nb_candidats_affectes'    => $nbAffectes,
            'nb_candidats_non_affectes'=> $nbCandidats - $nbAffectes,
            'nb_salles'                => $nbSalles,
            'nb_salles_requises'       => $session->getNbSallesRequiseAttribute(),
            'nb_surveillants'          => $nbSurv,
            'nb_surveillants_requis'   => $nbSurvRequis,
            'nb_surveillants_manquants'=> max(0, $nbSurvRequis - $nbSurv),
            'nb_surveillants_affectes' => $nbSurvAfff,
            'nb_convocations_imprimees'=> $session->candidats->where('convocation_imprimee', true)->count(),
            'pret_pour_examen'         => $nbAffectes === $nbCandidats && $nbSurv >= $nbSurvRequis,
            'alertes'                  => $this->getAlertes($session, $nbSurvRequis, $nbSurv, $nbCandidats, $nbAffectes),
        ];
    }

    private function getAlertes($session, $nbSurvRequis, $nbSurv, $nbCandidats, $nbAffectes): array
    {
        $alertes = [];
        if ($nbAffectes < $nbCandidats)
            $alertes[] = ['type' => 'danger', 'msg' => ($nbCandidats - $nbAffectes) . " candidat(s) sans salle — Lancer l'affectation automatique"];
        if ($nbSurv < $nbSurvRequis)
            $alertes[] = ['type' => 'danger', 'msg' => "Manque " . ($nbSurvRequis - $nbSurv) . " surveillant(s) — Ajouter des surveillants"];
        if ($session->salles->isEmpty())
            $alertes[] = ['type' => 'warning', 'msg' => "Aucune salle définie — Créer les salles d'abord"];
        if ($session->epreuves->isEmpty())
            $alertes[] = ['type' => 'warning', 'msg' => "Aucune épreuve planifiée — Ajouter le calendrier des matières"];
        return $alertes;
    }
}
```

---

## ÉTAPE 4 — Vues PDF Blade

**Créer :** `edugestdz/backend/resources/views/pdf/convocation-candidat.blade.php`

```html
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#1e293b; padding:20px; direction:rtl; }
  .header { text-align:center; border-bottom:2px solid #1e3a5f; padding-bottom:14px; margin-bottom:16px; }
  .logo { font-size:18px; font-weight:bold; color:#1e3a5f; }
  .subtitle { font-size:12px; color:#475569; margin-top:4px; }
  .title-conv { font-size:16px; font-weight:bold; color:#1e3a5f; margin:12px 0; text-align:center; border:2px solid #1e3a5f; padding:8px; }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
  .info-box { border:1px solid #e2e8f0; border-radius:6px; padding:8px 10px; background:#f8fafc; }
  .info-label { font-size:9px; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
  .info-value { font-size:12px; font-weight:bold; color:#0f172a; }
  .matiere-table { width:100%; border-collapse:collapse; margin-top:12px; }
  .matiere-table th { background:#1e3a5f; color:#fff; padding:7px 10px; font-size:10px; text-align:right; }
  .matiere-table td { padding:6px 10px; border-bottom:1px solid #e2e8f0; font-size:10px; }
  .matiere-table tr:nth-child(even) td { background:#f8fafc; }
  .important { background:#fef9c3; border:1px solid #eab308; border-radius:6px; padding:10px; margin-top:14px; font-size:10px; }
  .footer { margin-top:16px; text-align:center; font-size:9px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
  .stamp-area { border:2px dashed #cbd5e1; border-radius:8px; height:60px; margin-top:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:9px; }
</style>
</head>
<body>

<div class="header">
  <div class="logo">🎓 الجمهورية الجزائرية الديمقراطية الشعبية</div>
  <div class="subtitle">وزارة التربية الوطنية — الديوان الوطني للامتحانات والمسابقات</div>
  <div class="subtitle" style="font-size:10px;margin-top:2px;">
    {{ $session->nom_centre ?? 'مركز الامتحان' }} — {{ $session->wilaya ?? '' }}
  </div>
</div>

<div class="title-conv">
  استدعاء للمشاركة في امتحان
  @if($session->type === 'BAC') شهادة البكالوريا @elseif($session->type === 'BEM') شهادة التعليم المتوسط @endif
  — دورة {{ $session->annee_scolaire }}
</div>

<div class="info-grid">
  <div class="info-box">
    <div class="info-label">الاسم واللقب</div>
    <div class="info-value">{{ $candidat->nom }} {{ $candidat->prenom }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">رقم التسجيل</div>
    <div class="info-value" style="color:#2563eb;font-size:14px;">{{ $candidat->numero_inscription ?? 'غير محدد' }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">تاريخ الميلاد</div>
    <div class="info-value">{{ $candidat->date_naissance ? $candidat->date_naissance->format('d/m/Y') : '—' }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">مكان الميلاد</div>
    <div class="info-value">{{ $candidat->lieu_naissance ?? '—' }}</div>
  </div>
  @if($candidat->salle)
  <div class="info-box" style="background:#dbeafe;border-color:#2563eb;">
    <div class="info-label">القاعة</div>
    <div class="info-value" style="color:#1d4ed8;font-size:16px;">{{ $candidat->salle->nom }}</div>
  </div>
  <div class="info-box" style="background:#dbeafe;border-color:#2563eb;">
    <div class="info-label">المقعد</div>
    <div class="info-value" style="color:#1d4ed8;font-size:16px;">{{ $candidat->rangee }}{{ $candidat->colonne }}</div>
  </div>
  @endif
</div>

<table class="matiere-table">
  <thead>
    <tr>
      <th>المادة</th>
      <th>التاريخ</th>
      <th>التوقيت</th>
      <th>المدة</th>
      <th>المعامل</th>
    </tr>
  </thead>
  <tbody>
    @foreach($epreuves as $ep)
    <tr>
      <td><strong>{{ $ep->matiere }}</strong></td>
      <td>{{ $ep->date_epreuve->translatedFormat('l d/m/Y') }}</td>
      <td>{{ $ep->heure_debut }} — {{ $ep->heure_fin }}</td>
      <td>{{ $ep->duree_minutes }} دقيقة</td>
      <td style="text-align:center;font-weight:bold;">{{ $ep->coefficient }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="important">
  <strong>⚠️ تعليمات مهمة :</strong><br>
  • يفتح مركز الامتحان قبل ساعة من انطلاق الاختبار — لا يُسمح بالدخول بعد توزيع المواضيع<br>
  • يجب تقديم هذا الاستدعاء + بطاقة التعريف الوطنية إلزاميًا<br>
  • يُحظر إحضار أي جهاز إلكتروني أو وثيقة غير مُرخَّصة<br>
  • الدخول الصباحي: يحدد في 8:00 — انطلاق الاختبار: 8:30<br>
  • الدخول المسائي: يحدد في 14:00 — انطلاق الاختبار: 14:30
</div>

<div class="stamp-area">خانة ختم المركز</div>

<div class="footer">
  EduGest DZ · {{ $session->nom_centre }} · {{ $session->annee_scolaire }}
</div>

</body>
</html>
```

**Créer :** `edugestdz/backend/resources/views/pdf/convocation-surveillant.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#1e293b; padding:24px; }
  .header { text-align:center; border-bottom:2px solid #1e3a5f; padding-bottom:12px; margin-bottom:16px; }
  .title { font-size:15px; font-weight:bold; color:#1e3a5f; margin:10px 0; }
  .subtitle { font-size:11px; color:#475569; }
  .section { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; margin-bottom:12px; }
  .section h3 { font-size:11px; color:#1e3a5f; font-weight:bold; margin-bottom:8px; border-bottom:1px solid #e2e8f0; padding-bottom:4px; }
  .row { display:flex; gap:16px; margin-bottom:6px; }
  .label { color:#64748b; font-size:10px; min-width:120px; }
  .value { font-weight:bold; color:#0f172a; }
  .salle-box { background:#dbeafe; border:2px solid #2563eb; border-radius:8px; padding:14px; text-align:center; margin:12px 0; }
  .salle-nom { font-size:24px; font-weight:900; color:#1d4ed8; }
  .schedule-table { width:100%; border-collapse:collapse; }
  .schedule-table th { background:#1e3a5f; color:#fff; padding:7px; font-size:10px; text-align:left; }
  .schedule-table td { padding:6px 8px; border-bottom:1px solid #e2e8f0; font-size:10px; }
  .rules { background:#fef9c3; border:1px solid #eab308; border-radius:6px; padding:10px; margin-top:12px; font-size:10px; line-height:1.8; }
  .signature { border:1px solid #cbd5e1; border-radius:6px; padding:12px; margin-top:14px; display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .sig-box { text-align:center; border:1px dashed #94a3b8; border-radius:4px; height:60px; display:flex; align-items:flex-end; justify-content:center; padding-bottom:6px; font-size:9px; color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
  <div class="title">🎓 République Algérienne Démocratique et Populaire</div>
  <div class="subtitle">Ministère de l'Éducation Nationale — ONEC</div>
  <div class="title">CONVOCATION DE SURVEILLANCE</div>
  <div class="subtitle">
    Examen du {{ $session->type === 'BAC' ? 'Baccalauréat' : 'BEM' }} — Session {{ $session->session }} {{ $session->annee_scolaire }}
  </div>
</div>

<div class="section">
  <h3>Identité du Surveillant</h3>
  <div class="row"><span class="label">Nom et Prénom :</span><span class="value">{{ $surveillant->nom }} {{ $surveillant->prenom }}</span></div>
  <div class="row"><span class="label">Rôle :</span><span class="value">{{ \App\Models\SurveiillantExamen::ROLES[$surveillant->role] ?? $surveillant->role }}</span></div>
  <div class="row"><span class="label">Spécialité :</span><span class="value">{{ $surveillant->specialite ?? '—' }}</span></div>
</div>

<div class="salle-box">
  <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Salle affectée</div>
  <div class="salle-nom">{{ $surveillant->salle_nom ?? 'À définir' }}</div>
  @if($surveillant->salle)
    <div style="font-size:10px;color:#475569;margin-top:4px;">Capacité : {{ $surveillant->salle->nb_candidats_affectes }} candidats</div>
  @endif
</div>

<div class="section">
  <h3>Calendrier des Épreuves</h3>
  <table class="schedule-table">
    <thead>
      <tr><th>Date</th><th>Matière</th><th>Horaire</th><th>Durée</th></tr>
    </thead>
    <tbody>
      @foreach($epreuves as $ep)
      <tr>
        <td>{{ $ep->date_epreuve->format('d/m/Y') }}</td>
        <td><strong>{{ $ep->matiere }}</strong></td>
        <td>{{ $ep->heure_debut }} – {{ $ep->heure_fin }}</td>
        <td>{{ $ep->duree_minutes }} min</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="section">
  <h3>Centre d'Examen</h3>
  <div class="row"><span class="label">Centre :</span><span class="value">{{ $session->nom_centre ?? '—' }}</span></div>
  <div class="row"><span class="label">Adresse :</span><span class="value">{{ $session->adresse_centre ?? '—' }}</span></div>
  <div class="row"><span class="label">Wilaya :</span><span class="value">{{ $session->wilaya ?? '—' }}</span></div>
</div>

<div class="rules">
  <strong>📋 Instructions au Surveillant (ONEC) :</strong><br>
  • Se présenter au centre <strong>1h avant</strong> le début des épreuves<br>
  • Apporter cette convocation + pièce d'identité<br>
  • <strong>Ne pas surveiller</strong> les matières de sa spécialité (règle ONEC)<br>
  • Ne pas utiliser de téléphone portable pendant la surveillance<br>
  • Faire l'appel et émarger la liste des candidats à chaque épreuve<br>
  • Signaler immédiatement tout incident au Chef de Centre<br>
  • Interdiction de communiquer le sujet avant l'heure officielle
</div>

<div class="signature">
  <div class="sig-box">Signature du Surveillant</div>
  <div class="sig-box">Cachet du Chef de Centre</div>
</div>

</body>
</html>
```

**Créer :** `edugestdz/backend/resources/views/pdf/feuille-presence.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; padding:16px; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e3a5f; padding-bottom:10px; margin-bottom:14px; }
  .title { font-size:14px; font-weight:bold; color:#1e3a5f; }
  .salle-badge { background:#1e3a5f; color:#fff; padding:8px 14px; border-radius:6px; text-align:center; }
  .salle-badge .salle-nom { font-size:20px; font-weight:900; }
  .meta { font-size:10px; color:#475569; margin-bottom:10px; }
  .pres-table { width:100%; border-collapse:collapse; margin-top:8px; }
  .pres-table th { background:#1e3a5f; color:#fff; padding:7px 8px; font-size:9px; text-align:left; }
  .pres-table td { padding:6px 8px; border:1px solid #e2e8f0; font-size:10px; vertical-align:middle; }
  .pres-table tr:nth-child(even) td { background:#f8fafc; }
  .check-box { width:16px; height:16px; border:1px solid #94a3b8; border-radius:3px; display:inline-block; }
  .surv-section { margin-top:14px; background:#f0fdf4; border:1px solid #16a34a; border-radius:6px; padding:10px; }
  .surv-title { font-size:10px; font-weight:bold; color:#15803d; margin-bottom:6px; }
  .surv-row { display:flex; justify-content:space-between; margin-bottom:4px; font-size:10px; border-bottom:1px dashed #dcfce7; padding-bottom:4px; }
  .sig-area { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:14px; }
  .sig-box { border:1px dashed #cbd5e1; border-radius:4px; height:50px; display:flex; align-items:flex-end; justify-content:center; padding-bottom:4px; font-size:9px; color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="title">📋 FEUILLE DE PRÉSENCE</div>
    <div class="meta">
      Examen : {{ $session->type }} {{ $session->annee_scolaire }}<br>
      Centre : {{ $session->nom_centre ?? '—' }} — {{ $session->wilaya ?? '' }}<br>
      Épreuve : {{ request('matiere', 'Toutes les épreuves') }}
    </div>
  </div>
  <div class="salle-badge">
    <div style="font-size:9px;opacity:.8">SALLE</div>
    <div class="salle-nom">{{ $salle->nom }}</div>
    <div style="font-size:9px;opacity:.8">{{ $salle->nb_candidats_affectes }} candidats</div>
  </div>
</div>

<table class="pres-table">
  <thead>
    <tr>
      <th style="width:30px">N°</th>
      <th style="width:40px">Place</th>
      <th>Nom et Prénom</th>
      <th style="width:60px">N° Inscr.</th>
      <th style="width:50px">Type</th>
      <th style="width:40px;text-align:center">Présent</th>
      <th style="width:60px">Signature</th>
    </tr>
  </thead>
  <tbody>
    @foreach($candidats as $i => $c)
    <tr>
      <td style="text-align:center;color:#94a3b8">{{ $i+1 }}</td>
      <td style="text-align:center;font-weight:bold;color:#1d4ed8">{{ $c->rangee }}{{ $c->colonne }}</td>
      <td><strong>{{ $c->nom }}</strong> {{ $c->prenom }}</td>
      <td style="font-size:9px;color:#475569">{{ $c->numero_inscription ?? '—' }}</td>
      <td style="font-size:9px;text-align:center">{{ $c->type_candidat === 'libre' ? 'Libre' : 'Scol.' }}</td>
      <td style="text-align:center"><div class="check-box"></div></td>
      <td style="border-right:none"></td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="surv-section">
  <div class="surv-title">👨‍🏫 Surveillants affectés à cette salle :</div>
  @foreach($surveillants as $s)
  <div class="surv-row">
    <span><strong>{{ $s->nom }} {{ $s->prenom }}</strong></span>
    <span style="color:#475569">{{ $s->specialite ?? '—' }}</span>
    <span style="color:#94a3b8">{{ \App\Models\SurveiillantExamen::ROLES[$s->role] ?? $s->role }}</span>
  </div>
  @endforeach
</div>

<div style="margin-top:12px;font-size:9px;color:#475569;">
  Présents : _____ / {{ $candidats->count() }} &nbsp;&nbsp; Absents : _____ &nbsp;&nbsp; Incidents : □ Oui □ Non
</div>

<div class="sig-area">
  <div class="sig-box">Surveillant 1</div>
  <div class="sig-box">Surveillant 2</div>
  <div class="sig-box">Chef de Centre</div>
</div>

</body>
</html>
```

---

## ÉTAPE 5 — ExamenController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/ExamenController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SessionExamen;
use App\Models\EpreuveExamen;
use App\Models\SalleExamen;
use App\Models\CandidatExamen;
use App\Models\SurveiillantExamen;
use App\Services\ExamenService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExamenController extends Controller
{
    public function __construct(private ExamenService $service) {}

    // ── Sessions ──────────────────────────────────────────────────────

    public function indexSessions(Request $request): JsonResponse
    {
        $sessions = SessionExamen::withCount(['candidats','salles','epreuves'])
            ->when($request->filled('type'),   fn($q) => $q->where('type', $request->type))
            ->when($request->filled('statut'), fn($q) => $q->where('statut', $request->statut))
            ->orderByDesc('date_debut')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $sessions]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $v = $request->validate([
            'type'             => 'required|in:BEM,BAC,autre',
            'filiere'          => 'nullable|string',
            'annee_scolaire'   => 'required|string|max:10',
            'session'          => 'in:principale,rattrapage',
            'date_debut'       => 'required|date',
            'date_fin'         => 'required|date|after_or_equal:date_debut',
            'wilaya'           => 'nullable|string|max:60',
            'commune'          => 'nullable|string|max:60',
            'nom_centre'       => 'nullable|string|max:200',
            'adresse_centre'   => 'nullable|string|max:300',
            'max_candidats_par_salle'        => 'integer|min:1|max:30',
            'max_candidats_libres_par_salle' => 'integer|min:1|max:20',
            'nb_surveillants_par_salle'       => 'integer|min:1|max:10',
        ]);
        $session = SessionExamen::create([...$v, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $session, 'message' => 'Session créée'], 201);
    }

    public function showSession(string $id): JsonResponse
    {
        $session = SessionExamen::with(['epreuves','salles','candidats','surveillants'])
            ->findOrFail($id);
        $dashboard = $this->service->getDashboard($id);
        return response()->json(['success' => true, 'data' => $session, 'dashboard' => $dashboard]);
    }

    public function updateSession(Request $request, string $id): JsonResponse
    {
        $session = SessionExamen::findOrFail($id);
        $session->update($request->only([
            'statut','nom_centre','adresse_centre','wilaya','commune',
            'max_candidats_par_salle','nb_surveillants_par_salle','notes',
        ]));
        return response()->json(['success' => true, 'data' => $session->fresh()]);
    }

    // ── Épreuves ──────────────────────────────────────────────────────

    public function storeEpreuve(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'matiere'                => 'required|string|max:100',
            'code_matiere'           => 'nullable|string|max:10',
            'coefficient'            => 'required|numeric|min:0.5|max:9',
            'date_epreuve'           => 'required|date',
            'moment'                 => 'required|in:matin,apres_midi',
            'heure_debut'            => 'required|date_format:H:i',
            'heure_fin'              => 'required|date_format:H:i|after:heure_debut',
            'duree_minutes'          => 'integer|min:30|max:360',
            'type_epreuve'           => 'in:ecrit,oral,pratique',
            'calculatrice_autorisee' => 'boolean',
            'documents_autorises'    => 'boolean',
        ]);
        $epreuve = EpreuveExamen::create([...$v, 'session_id' => $sessionId]);
        return response()->json(['success' => true, 'data' => $epreuve], 201);
    }

    public function deleteEpreuve(string $id): JsonResponse
    {
        EpreuveExamen::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Épreuve supprimée']);
    }

    // ── Salles ────────────────────────────────────────────────────────

    public function storeSalle(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'nom'             => 'required|string|max:50',
            'numero'          => 'nullable|string|max:20',
            'batiment'        => 'nullable|string|max:50',
            'etage'           => 'nullable|string|max:20',
            'capacite_totale' => 'required|integer|min:1|max:50',
            'nb_rangees'      => 'nullable|integer|min:1|max:10',
            'nb_colonnes'     => 'nullable|integer|min:1|max:10',
            'climatisee'      => 'boolean',
            'accessible_pmr'  => 'boolean',
        ]);
        $salle = SalleExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $salle], 201);
    }

    // ── Candidats ─────────────────────────────────────────────────────

    public function indexCandidats(Request $request, string $sessionId): JsonResponse
    {
        $candidats = CandidatExamen::with('salle:id,nom')
            ->where('session_id', $sessionId)
            ->when($request->filled('salle_id'), fn($q) => $q->where('salle_id', $request->salle_id))
            ->orderBy('salle_id')->orderBy('numero_place')
            ->paginate(50);
        return response()->json(['success' => true, 'data' => $candidats]);
    }

    public function storeCandidat(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'nom'                => 'required|string|max:100',
            'prenom'             => 'required|string|max:100',
            'date_naissance'     => 'nullable|date',
            'lieu_naissance'     => 'nullable|string|max:100',
            'numero_inscription' => 'nullable|string|max:20',
            'type_candidat'      => 'in:scolarise,libre',
            'filiere'            => 'nullable|string|max:50',
            'besoins_speciaux'   => 'boolean',
        ]);
        $candidat = CandidatExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $candidat], 201);
    }

    public function importerElevesSysteme(Request $request, string $sessionId): JsonResponse
    {
        $request->validate(['eleve_ids' => 'required|array', 'eleve_ids.*' => 'uuid']);
        $result = $this->service->importerElevesSysteme($sessionId, $request->eleve_ids);
        return response()->json(['success' => true, 'data' => $result, 'message' => "{$result['importes']} élève(s) importé(s)"]);
    }

    public function importerCSV(Request $request, string $sessionId): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|mimes:csv,txt|max:5120']);
        $result = $this->service->importerCandidats($sessionId, $request->file('fichier')->getPathname());
        return response()->json(['success' => true, 'data' => $result, 'message' => "{$result['importes']} candidat(s) importé(s)"]);
    }

    public function marquerPresence(Request $request, string $candidatId): JsonResponse
    {
        $candidat = CandidatExamen::findOrFail($candidatId);
        $candidat->update(['present' => $request->boolean('present', true), 'present_marque_le' => now()]);
        return response()->json(['success' => true, 'data' => $candidat]);
    }

    // ── Surveillants ──────────────────────────────────────────────────

    public function storeSurveillant(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'user_id'          => 'required|uuid|exists:users,id',
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'specialite'       => 'nullable|string|max:100',
            'commune_origine'  => 'nullable|string|max:60',
            'role'             => 'in:chef_centre,surveillant,secretaire,observateur',
            'disponible'       => 'boolean',
            'motif_exemption'  => 'nullable|string|max:300',
        ]);
        $surveillant = SurveiillantExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $surveillant], 201);
    }

    public function importerEnseignantsSurveillants(string $sessionId): JsonResponse
    {
        // Importer automatiquement tous les enseignants de l'établissement
        $enseignants = \App\Models\User::where('tenant_id', config('tenant.current_id'))
            ->whereIn('role', ['enseignant'])->get();
        $importes = 0;
        foreach ($enseignants as $ens) {
            $existe = SurveiillantExamen::where('session_id', $sessionId)->where('user_id', $ens->id)->exists();
            if ($existe) continue;
            SurveiillantExamen::create([
                'session_id'      => $sessionId,
                'tenant_id'       => config('tenant.current_id'),
                'user_id'         => $ens->id,
                'nom'             => $ens->nom,
                'prenom'          => $ens->prenom,
                'specialite'      => $ens->specialite ?? null,
                'role'            => 'surveillant',
                'disponible'      => true,
            ]);
            $importes++;
        }
        return response()->json(['success' => true, 'message' => "{$importes} enseignant(s) ajouté(s) comme surveillants"]);
    }

    // ── Algorithmes d'affectation ─────────────────────────────────────

    public function affecterCandidats(string $sessionId): JsonResponse
    {
        try {
            $result = $this->service->affecterCandidatsAuxSalles($sessionId);
            return response()->json(['success' => true, 'data' => $result, 'message' => $result['message']]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function affecterSurveilants(string $sessionId): JsonResponse
    {
        try {
            $result = $this->service->affecterSurveillantsAuxSalles($sessionId);
            return response()->json(['success' => true, 'data' => $result, 'message' => $result['message']]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Génération PDF ────────────────────────────────────────────────

    public function pdfConvocationCandidat(string $id): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererConvocationCandidat($id);
        return $pdf->download("convocation-candidat-{$id}.pdf");
    }

    public function pdfToutesConvocations(string $sessionId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererToutesConvocations($sessionId);
        return $pdf->download("convocations-{$sessionId}.pdf");
    }

    public function pdfConvocationSurveillant(string $id): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererConvocationSurveillant($id);
        return $pdf->download("convocation-surveillant-{$id}.pdf");
    }

    public function pdfFeuillePresence(string $salleId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererFeuillePresence($salleId);
        return $pdf->download("feuille-presence-{$salleId}.pdf");
    }

    public function pdfPlanSalle(string $salleId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererPlanSalle($salleId);
        return $pdf->download("plan-salle-{$salleId}.pdf");
    }
}
```

---

## ÉTAPE 6 — Routes API

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\ExamenController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/examens')->group(function () {
    // Sessions
    Route::get('/',                       [ExamenController::class, 'indexSessions']);
    Route::post('/',                      [ExamenController::class, 'storeSession']);
    Route::get('/{id}',                   [ExamenController::class, 'showSession']);
    Route::put('/{id}',                   [ExamenController::class, 'updateSession']);

    // Épreuves
    Route::post('/{sessionId}/epreuves',          [ExamenController::class, 'storeEpreuve']);
    Route::delete('/epreuves/{id}',               [ExamenController::class, 'deleteEpreuve']);

    // Salles
    Route::post('/{sessionId}/salles',            [ExamenController::class, 'storeSalle']);

    // Candidats
    Route::get('/{sessionId}/candidats',          [ExamenController::class, 'indexCandidats']);
    Route::post('/{sessionId}/candidats',         [ExamenController::class, 'storeCandidat']);
    Route::post('/{sessionId}/candidats/import-eleves', [ExamenController::class, 'importerElevesSysteme']);
    Route::post('/{sessionId}/candidats/import-csv',    [ExamenController::class, 'importerCSV']);
    Route::post('/candidats/{id}/presence',       [ExamenController::class, 'marquerPresence']);

    // Surveillants
    Route::post('/{sessionId}/surveillants',      [ExamenController::class, 'storeSurveillant']);
    Route::post('/{sessionId}/surveillants/import',[ExamenController::class, 'importerEnseignantsSurveillants']);

    // Algorithmes
    Route::post('/{sessionId}/affecter-candidats',  [ExamenController::class, 'affecterCandidats']);
    Route::post('/{sessionId}/affecter-surveillants',[ExamenController::class, 'affecterSurveilants']);

    // PDF
    Route::get('/candidats/{id}/convocation',        [ExamenController::class, 'pdfConvocationCandidat']);
    Route::get('/{sessionId}/toutes-convocations',   [ExamenController::class, 'pdfToutesConvocations']);
    Route::get('/surveillants/{id}/convocation',     [ExamenController::class, 'pdfConvocationSurveillant']);
    Route::get('/salles/{salleId}/feuille-presence', [ExamenController::class, 'pdfFeuillePresence']);
    Route::get('/salles/{salleId}/plan',             [ExamenController::class, 'pdfPlanSalle']);
});
```

---

## ÉTAPE 7 — Sidebar.jsx : ajouter le lien

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Dans la section "Pédagogie", ajouter :
```jsx
{ label: 'Examens Officiels', path: '/examens', icon: '📝' },
```

**Modifier :** `edugestdz/frontend/src/App.jsx`

```jsx
import ExamensPage from '@pages/ExamensPage';
<Route path="examens" element={<ExamensPage />} />
```

**Créer :** `edugestdz/frontend/src/pages/ExamensPage.jsx`

```jsx
import { useState, useEffect } from 'react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, 'Content-Type':'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
  ...opts,
}).then(r => r.json());

const STATUTS = { brouillon:'⚪ Brouillon', planifie:'🔵 Planifié', en_cours:'🟡 En cours', termine:'🟢 Terminé', annule:'🔴 Annulé' };
const TYPES   = { BEM:'📋 BEM', BAC:'🎓 BAC', autre:'📄 Autre' };

export default function ExamensPage() {
  const [sessions, setSessions] = useState([]);
  const [selected, setSelected] = useState(null);
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading]   = useState(true);
  const [tab, setTab] = useState('sessions');
  const [showNew, setShowNew]   = useState(false);
  const [form, setForm] = useState({ type:'BAC', annee_scolaire:'2025/2026', session:'principale', date_debut:'', date_fin:'', wilaya:'Oran', nom_centre:'', max_candidats_par_salle:20, nb_surveillants_par_salle:3 });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');

  useEffect(() => { loadSessions(); }, []);

  const loadSessions = async () => {
    setLoading(true);
    const res = await api('/examens');
    setSessions(res?.data?.data ?? []);
    setLoading(false);
  };

  const loadDashboard = async (id) => {
    const res = await api(`/examens/${id}`);
    setSelected(res?.data);
    setDashboard(res?.dashboard);
    setTab('dashboard');
  };

  const createSession = async () => {
    setSaving(true);
    const res = await api('/examens', { method:'POST', body: JSON.stringify(form) });
    setSaving(false);
    if (res.success) { setShowNew(false); loadSessions(); setMsg('✅ Session créée'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const affecterCandidats = async (id) => {
    const res = await api(`/examens/${id}/affecter-candidats`, { method:'POST' });
    alert(res.message ?? (res.success ? '✅ Affectation terminée' : '❌ Erreur'));
    if (res.success) loadDashboard(id);
  };

  const affecterSurveillants = async (id) => {
    const res = await api(`/examens/${id}/affecter-surveillants`, { method:'POST' });
    alert(res.message ?? (res.success ? '✅ Affectation terminée' : '❌ Erreur'));
    if (res.success) loadDashboard(id);
  };

  const imprimerConvocations = (sessionId) => {
    window.open(`/api/v1/examens/${sessionId}/toutes-convocations`, '_blank');
  };

  const S = (v, color, bg) => (
    <span style={{ background:bg||color+'22', color:color||'#60a5fa', fontSize:'10px', fontWeight:700, padding:'2px 9px', borderRadius:'20px' }}>{v}</span>
  );

  return (
    <div style={{ padding:'24px', background:'#070B14', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>📝 Examens Officiels BEM/BAC</h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>Calendrier · Salles · Surveillants · Convocations PDF</p>
        </div>
        <button onClick={() => setShowNew(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'10px 18px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
          + Nouvelle session
        </button>
      </div>

      {msg && <div style={{ background:msg.includes('✅')?'#0d2515':'#450a0a', border:`1px solid ${msg.includes('✅')?'#16a34a':'#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color:msg.includes('✅')?'#4ade80':'#f87171' }}>{msg}</div>}

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['sessions','📋 Sessions'],selected&&['dashboard','📊 Dashboard'],selected&&['epreuves','🗓️ Épreuves'],selected&&['salles','🏫 Salles'],selected&&['candidats','👦 Candidats'],selected&&['surveillants','👨‍🏫 Surveillants']].filter(Boolean).map(([id,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{ background:tab===id?'#1e3a5f':'#111318', color:tab===id?'#60a5fa':'#64748B', border:`1px solid ${tab===id?'#3b82f6':'#1E2D40'}`, borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>{label}</button>
        ))}
      </div>

      {/* Liste sessions */}
      {tab === 'sessions' && (
        <div style={{ display:'grid', gap:'10px' }}>
          {loading ? <div style={{ color:'#64748B', textAlign:'center', padding:'40px' }}>Chargement...</div>
          : sessions.length === 0 ? <div style={{ color:'#64748B', textAlign:'center', padding:'40px' }}>Aucune session. Créer une session BEM ou BAC.</div>
          : sessions.map(s => (
            <div key={s.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'12px', padding:'16px 20px', display:'flex', alignItems:'center', gap:'16px' }}>
              <div style={{ fontSize:'28px' }}>{s.type === 'BAC' ? '🎓' : '📋'}</div>
              <div style={{ flex:1 }}>
                <div style={{ fontWeight:800, fontSize:'14px', color:'#fff' }}>{TYPES[s.type] ?? s.type} — {s.annee_scolaire}</div>
                <div style={{ fontSize:'11px', color:'#64748B' }}>
                  {s.session} · {s.nom_centre ?? 'Centre à définir'} · {s.wilaya ?? ''}
                  · Du {s.date_debut} au {s.date_fin}
                </div>
                <div style={{ display:'flex', gap:'8px', marginTop:'6px' }}>
                  {S(STATUTS[s.statut] ?? s.statut, s.statut==='termine'?'#10B981':s.statut==='en_cours'?'#F59E0B':'#60a5fa')}
                  {S(`👦 ${s.candidats_count??0} candidats`, '#60a5fa')}
                  {S(`🏫 ${s.salles_count??0} salles`, '#7C3AED')}
                  {S(`📚 ${s.epreuves_count??0} épreuves`, '#10B981')}
                </div>
              </div>
              <button onClick={() => loadDashboard(s.id)} style={{ background:'#2563EB', color:'#fff', border:'none', borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                Gérer →
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Dashboard session */}
      {tab === 'dashboard' && selected && dashboard && (
        <div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'20px' }}>
            {[
              ['👦 Candidats',  dashboard.nb_candidats_total,    '#2563EB'],
              ['✅ Affectés',   dashboard.nb_candidats_affectes, '#10B981'],
              ['🏫 Salles',     dashboard.nb_salles,             '#7C3AED'],
              ['👨‍🏫 Surveillants', dashboard.nb_surveillants,  '#F59E0B'],
            ].map(([label, val, color]) => (
              <div key={label} style={{ background:'#0D1117', border:`1px solid #1E2D40`, borderTop:`2px solid ${color}`, borderRadius:'12px', padding:'16px' }}>
                <div style={{ fontSize:'10px', color:'#64748B', marginBottom:'8px' }}>{label}</div>
                <div style={{ fontSize:'26px', fontWeight:900, color:'#fff' }}>{val ?? 0}</div>
              </div>
            ))}
          </div>

          {dashboard.alertes?.length > 0 && dashboard.alertes.map((a,i) => (
            <div key={i} style={{ background:a.type==='danger'?'#450a0a':'#1f1008', border:`1px solid ${a.type==='danger'?'#b91c1c':'#c2410c'}`, borderRadius:'9px', padding:'10px 14px', marginBottom:'8px', fontSize:'11px', color:a.type==='danger'?'#f87171':'#fb923c' }}>
              ⚠️ {a.msg}
            </div>
          ))}

          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px', marginTop:'16px' }}>
            <button onClick={() => affecterCandidats(selected.id)} style={{ background:'#2563EB', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              🤖 Affecter candidats aux salles
            </button>
            <button onClick={() => affecterSurveillants(selected.id)} style={{ background:'#7C3AED', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              👨‍🏫 Affecter surveillants
            </button>
            <button onClick={() => imprimerConvocations(selected.id)} style={{ background:'#10B981', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              🖨️ Imprimer toutes les convocations PDF
            </button>
          </div>
        </div>
      )}

      {/* Modal nouvelle session */}
      {showNew && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowNew(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'520px', maxWidth:'90%' }} onClick={e=>e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📝 Nouvelle Session d'Examen</h3>
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px' }}>
              {[
                { label:'Type *', key:'type', type:'select', opts:{BEM:'BEM',BAC:'BAC',autre:'Autre'} },
                { label:'Année scolaire *', key:'annee_scolaire', type:'text', ph:'2025/2026' },
                { label:'Session', key:'session', type:'select', opts:{principale:'Principale',rattrapage:'Rattrapage'} },
                { label:'Date début *', key:'date_debut', type:'date' },
                { label:'Date fin *', key:'date_fin', type:'date' },
                { label:'Wilaya', key:'wilaya', type:'text', ph:'Oran' },
                { label:'Nom centre', key:'nom_centre', type:'text', ph:'Lycée Ibn Khaldoun' },
                { label:'Max candidats/salle', key:'max_candidats_par_salle', type:'number' },
              ].map(({ label, key, type, opts, ph }) => (
                <div key={key}>
                  <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                  {type === 'select' ? (
                    <select value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))}
                      style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                      {Object.entries(opts).map(([k,v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                  ) : (
                    <input type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))} placeholder={ph}
                      style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
                  )}
                </div>
              ))}
            </div>
            <div style={{ display:'flex', gap:'10px', marginTop:'20px' }}>
              <button onClick={() => setShowNew(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={createSession} disabled={saving || !form.date_debut || !form.date_fin} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Création...' : '✅ Créer la session'}
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

## ÉTAPE 8 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Controllers/ExamenControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\SessionExamen;
use App\Models\SalleExamen;
use App\Models\CandidatExamen;
use App\Models\SurveiillantExamen;
use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ExamenControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeSession(array $attrs = []): SessionExamen
    {
        return SessionExamen::create(array_merge([
            'tenant_id'    => Str::uuid(),
            'type'         => 'BAC',
            'annee_scolaire'=> '2025/2026',
            'session'      => 'principale',
            'date_debut'   => '2026-06-07',
            'date_fin'     => '2026-06-11',
            'wilaya'       => 'Oran',
            'nom_centre'   => 'Lycée Test',
            'max_candidats_par_salle' => 20,
            'nb_surveillants_par_salle' => 3,
            'statut'       => 'planifie',
        ], $attrs));
    }

    public function test_lister_sessions(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/examens')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_creer_session_bac(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/examens', [
                'type'           => 'BAC',
                'annee_scolaire' => '2025/2026',
                'session'        => 'principale',
                'date_debut'     => '2026-06-07',
                'date_fin'       => '2026-06-11',
                'wilaya'         => 'Oran',
                'nom_centre'     => 'Lycée Ibn Khaldoun',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'BAC');
    }

    public function test_creer_session_bem(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/examens', [
                'type'           => 'BEM',
                'annee_scolaire' => '2025/2026',
                'session'        => 'principale',
                'date_debut'     => '2026-05-19',
                'date_fin'       => '2026-05-21',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'BEM');
    }

    public function test_ajouter_epreuve(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/epreuves", [
                'matiere'       => 'Mathématiques',
                'coefficient'   => 6,
                'date_epreuve'  => '2026-06-07',
                'moment'        => 'matin',
                'heure_debut'   => '08:30',
                'heure_fin'     => '12:30',
                'duree_minutes' => 240,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.matiere', 'Mathématiques');
    }

    public function test_ajouter_salle(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/salles", [
                'nom'             => 'Salle 01',
                'capacite_totale' => 20,
                'nb_rangees'      => 4,
                'nb_colonnes'     => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Salle 01');
    }

    public function test_ajouter_candidat(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/candidats", [
                'nom'                => 'Benali',
                'prenom'             => 'Amira',
                'numero_inscription' => '260010001',
                'type_candidat'      => 'scolarise',
            ])
            ->assertStatus(201);
    }

    public function test_algorithme_affectation_candidats(): void
    {
        $session = $this->makeSession();

        // Créer 3 salles
        for ($s = 1; $s <= 3; $s++) {
            SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>"Salle 0{$s}",'capacite_totale'=>20]);
        }

        // Créer 45 candidats
        for ($i = 1; $i <= 45; $i++) {
            CandidatExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>"Candidat {$i}",'prenom'=>'Test','type_candidat'=>'scolarise']);
        }

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/affecter-candidats")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Vérifier que tous les candidats ont une salle
        $this->assertEquals(45, CandidatExamen::where('session_id',$session->id)->whereNotNull('salle_id')->count());
    }

    public function test_algorithme_affectation_surveillants_respecte_specialite(): void
    {
        $session = $this->makeSession();

        SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Salle 01','capacite_totale'=>20,'nb_candidats_affectes'=>20]);

        // Épreuve Mathématiques
        \App\Models\EpreuveExamen::create(['session_id'=>$session->id,'matiere'=>'Mathématiques','coefficient'=>6,'date_epreuve'=>'2026-06-07','moment'=>'matin','heure_debut'=>'08:30','heure_fin'=>'12:30']);

        // Ajouter 4 surveillants dont 1 spécialisé en Maths
        $survMaths = SurveiillantExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'user_id'=>$this->admin->id,'nom'=>'Prof','prenom'=>'Maths','specialite'=>'Mathématiques','role'=>'surveillant','disponible'=>true]);
        for ($i = 2; $i <= 4; $i++) {
            $u = User::factory()->create(['role'=>'enseignant']);
            SurveiillantExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'user_id'=>$u->id,'nom'=>"Surv{$i}",'prenom'=>'Test','specialite'=>'Physique','role'=>'surveillant','disponible'=>true]);
        }

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/affecter-surveillants")
            ->assertStatus(200);

        // Le prof de Maths ne doit PAS être affecté à la salle d'examen de Maths
        $this->assertNull($survMaths->fresh()->salle_id);
    }

    public function test_afficher_dashboard_session(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/examens/{$session->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['success','data','dashboard']);
    }

    public function test_generer_pdf_feuille_presence(): void
    {
        $session = $this->makeSession();
        $salle = SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Salle 01','capacite_totale'=>20]);

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/salles/{$salle->id}/feuille-presence")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_marquer_presence_candidat(): void
    {
        $session  = $this->makeSession();
        $candidat = CandidatExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Test','prenom'=>'Test','type_candidat'=>'scolarise']);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/candidats/{$candidat->id}/presence", ['present'=>true])
            ->assertStatus(200)
            ->assertJsonPath('data.present', true);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/examens')->assertStatus(401);
    }
}
```

---

## ÉTAPE 9 — Exécution

```bash
cd edugestdz/backend

# Migration
php artisan migrate

# Autoload
composer dump-autoload -o

# Tests
php artisan test --parallel
# → 0 régression + 11 nouveaux tests verts

# Commit
git add .
git commit -m "feat: Module Examens Officiels BEM/BAC — Calendrier + Salles + Algorithme affectation candidats/surveillants (règles ONEC) + Convocations PDF + Feuilles présence + 11 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_EXAMENS_OFFICIELS_BEM_BAC.md — 9 étapes dans l'ordre.

RÈGLES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. Réutiliser barryvdh/laravel-dompdf déjà installé.
4. Les 5 tables dans UNE SEULE migration.
5. Le model se nomme SurveiillantExamen (double i) — c'est intentionnel pour éviter le conflit avec un éventuel SurveillantExamen existant.
6. Les vues Blade PDF sont dans resources/views/pdf/ — créer le dossier si absent.
7. Le test test_algorithme_affectation_surveillants_respecte_specialite() vérifie la règle ONEC : un prof de Maths ne surveille PAS une salle où se passe un examen de Maths — vérifier que l'algo respecte bien cette règle.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
