# 🤖 MISSION DEEPSEEK — Module Bibliothèque Scolaire
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 432 ✅ · 0 régression

---

## CONTEXTE — Recherche effectuée

### Ce que font les meilleurs systèmes mondiaux (Vidyalaya, Fedena, CodeAchi)
- **Catalogue** : livres catalogués avec ISBN, auteur, éditeur, rayon, exemplaires
- **Prêts/Retours** : émission en 1 scan, retour automatique avec calcul amende
- **Amendes** : calcul automatique par jour de retard, ajouté au compte élève
- **Réservations** : livre emprunté → l'élève réserve → notifié quand disponible
- **Notifications** : SMS/Push 2 jours avant l'échéance + relance si retard
- **OPAC** : catalogue consultable par les élèves depuis l'app
- **Rapports** : livres en retard, top emprunteurs, inventaire, budget

### Règles adaptées au contexte algérien (recherche universités DZ)
- Durée prêt standard : **14 jours** (renouvelable 1 fois)
- Nombre max livres/élève : **3 livres simultanément**
- Amende retard : **50 DA par livre par jour** (configurable)
- Livre perdu : **prix de remplacement + 200 DA frais dossier**
- Catégories : Manuels scolaires · Parascolaire · Romans · Sciences · Islamique · Encyclopédies

### RÈGLES ABSOLUES
1. **0 régression** — les 418+ tests existants restent verts
2. **PostgreSQL uniquement** — jamais SQLite
3. **Multi-tenant** — chaque école a sa propre bibliothèque isolée
4. **Réutiliser** : SmsService, FirebaseService, ParentNotificationService existants
5. **Ne pas modifier** les contrôleurs existants

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : 5 tables bibliothèque

**Créer :**
`edugestdz/backend/database/migrations/2026_07_06_100000_create_bibliotheque_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Catalogue des livres ──────────────────────────────────────
        Schema::create('livres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('titre');
            $table->string('auteur')->nullable();
            $table->string('editeur')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->integer('annee_edition')->nullable();
            $table->string('categorie')->default('general');
            // Valeurs : manuel_scolaire | parascolaire | roman | sciences
            //           islamique | encyclopedie | histoire | langues | autre
            $table->string('niveau_scolaire')->nullable(); // ex: 3AS, 2AM
            $table->string('matiere')->nullable();         // ex: Mathématiques
            $table->string('langue')->default('ar');       // ar | fr | en
            $table->integer('nb_exemplaires')->default(1);
            $table->integer('nb_disponibles')->default(1);
            $table->string('rayon')->nullable();           // Rayon A, Étagère 3
            $table->string('cote')->nullable();            // Code de classification
            $table->string('code_barre')->nullable()->unique(); // Code barre unique
            $table->decimal('prix_remplacement', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'categorie'],    'idx_livre_tenant_cat');
            $table->index(['tenant_id', 'actif'],        'idx_livre_tenant_actif');
            $table->index(['tenant_id', 'nb_disponibles'],'idx_livre_dispo');
        });

        // ── 2. Prêts de livres ───────────────────────────────────────────
        Schema::create('prets_livres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('livre_id');
            $table->uuid('emprunteur_id');  // user_id (élève, enseignant, personnel)
            $table->string('emprunteur_type')->default('eleve');
            // Valeurs : eleve | enseignant | personnel
            $table->uuid('gere_par');       // user_id du bibliothécaire
            $table->date('date_pret');
            $table->date('date_retour_prevue');
            $table->date('date_retour_reelle')->nullable();
            $table->integer('nb_renouvellements')->default(0);
            $table->date('date_dernier_renouvellement')->nullable();
            $table->enum('statut', [
                'en_cours',    // livre emprunté, pas encore rendu
                'rendu',       // rendu dans les délais
                'en_retard',   // date dépassée, pas encore rendu
                'perdu',       // déclaré perdu
                'renouvele',   // en cours avec renouvellement
            ])->default('en_cours');
            $table->decimal('amende_calculee', 8, 2)->default(0);
            $table->decimal('amende_payee', 8, 2)->default(0);
            $table->boolean('amende_soldee')->default(false);
            $table->boolean('sms_rappel_envoye')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('livre_id')->references('id')->on('livres')->onDelete('cascade');
            $table->index(['livre_id', 'statut'],         'idx_pret_livre_statut');
            $table->index(['emprunteur_id', 'statut'],    'idx_pret_emprunteur');
            $table->index(['tenant_id', 'statut'],        'idx_pret_tenant_statut');
            $table->index(['date_retour_prevue', 'statut'],'idx_pret_echeance');
        });

        // ── 3. Réservations de livres ────────────────────────────────────
        Schema::create('reservations_livres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('livre_id');
            $table->uuid('reservant_id');  // élève ou enseignant
            $table->string('reservant_type')->default('eleve');
            $table->date('date_reservation');
            $table->date('date_expiration'); // réservation expire après 3 jours si non récupérée
            $table->enum('statut', ['en_attente', 'disponible', 'honoree', 'expiree', 'annulee'])
                ->default('en_attente');
            $table->boolean('notification_envoyee')->default(false);
            $table->timestamp('notifie_le')->nullable();
            $table->timestamps();

            $table->foreign('livre_id')->references('id')->on('livres')->onDelete('cascade');
            $table->index(['livre_id', 'statut'],      'idx_resa_livre_statut');
            $table->index(['reservant_id', 'statut'],  'idx_resa_emprunteur');
        });

        // ── 4. Configuration bibliothèque par tenant ─────────────────────
        Schema::create('config_bibliotheque', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->unique();
            $table->integer('duree_pret_jours')->default(14);     // durée prêt standard
            $table->integer('max_livres_eleve')->default(3);       // max simultané élève
            $table->integer('max_livres_enseignant')->default(5);  // max simultané enseignant
            $table->integer('max_renouvellements')->default(1);    // max renouvellements
            $table->decimal('amende_par_jour', 8, 2)->default(50); // DA/jour de retard
            $table->decimal('frais_livre_perdu', 8, 2)->default(200); // frais dossier si perdu
            $table->integer('rappel_avant_jours')->default(2);    // rappel X jours avant échéance
            $table->boolean('rappel_sms_actif')->default(true);
            $table->boolean('rappel_push_actif')->default(true);
            $table->boolean('amendes_bloquent_pret')->default(true); // amende impayée → bloque prêt
            $table->string('nom_bibliotheque')->nullable();
            $table->string('responsable_nom')->nullable();
            $table->time('heure_ouverture')->default('08:00');
            $table->time('heure_fermeture')->default('17:00');
            $table->timestamps();
        });

        // ── 5. Historique amendes ────────────────────────────────────────
        Schema::create('amendes_bibliotheque', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('pret_id');
            $table->uuid('emprunteur_id');
            $table->decimal('montant', 8, 2);
            $table->string('type')->default('retard');
            // Valeurs : retard | perte | deterioration
            $table->integer('nb_jours_retard')->nullable();
            $table->boolean('payee')->default(false);
            $table->decimal('montant_paye', 8, 2)->default(0);
            $table->timestamp('payee_le')->nullable();
            $table->uuid('encaissee_par')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('pret_id')->references('id')->on('prets_livres')->onDelete('cascade');
            $table->index(['emprunteur_id', 'payee'], 'idx_amende_emprunteur');
            $table->index(['tenant_id', 'payee'],     'idx_amende_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amendes_bibliotheque');
        Schema::dropIfExists('config_bibliotheque');
        Schema::dropIfExists('reservations_livres');
        Schema::dropIfExists('prets_livres');
        Schema::dropIfExists('livres');
    }
};
```

---

## ÉTAPE 2 — Models

**Créer :** `edugestdz/backend/app/Models/Livre.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Livre extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'livres';

    protected $fillable = [
        'tenant_id', 'titre', 'auteur', 'editeur', 'isbn',
        'annee_edition', 'categorie', 'niveau_scolaire', 'matiere',
        'langue', 'nb_exemplaires', 'nb_disponibles', 'rayon', 'cote',
        'code_barre', 'prix_remplacement', 'description', 'photo_url', 'actif',
    ];

    protected $casts = [
        'actif'            => 'boolean',
        'prix_remplacement'=> 'decimal:2',
        'nb_exemplaires'   => 'integer',
        'nb_disponibles'   => 'integer',
    ];

    public const CATEGORIES = [
        'manuel_scolaire' => 'Manuel scolaire',
        'parascolaire'    => 'Parascolaire',
        'roman'           => 'Roman / Littérature',
        'sciences'        => 'Sciences',
        'islamique'       => 'Islamique / Religion',
        'encyclopedie'    => 'Encyclopédie',
        'histoire'        => 'Histoire / Géographie',
        'langues'         => 'Langues',
        'autre'           => 'Autre',
    ];

    public function prets()
    {
        return $this->hasMany(PretLivre::class, 'livre_id');
    }

    public function pretsEnCours()
    {
        return $this->hasMany(PretLivre::class, 'livre_id')
            ->whereIn('statut', ['en_cours', 'en_retard', 'renouvele']);
    }

    public function reservations()
    {
        return $this->hasMany(ReservationLivre::class, 'livre_id')
            ->where('statut', 'en_attente')
            ->orderBy('created_at');
    }

    public function scopeDisponible($query)
    {
        return $query->where('nb_disponibles', '>', 0)->where('actif', true);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function isDisponible(): bool
    {
        return $this->nb_disponibles > 0;
    }

    // Génère un code barre simple si absent
    public function getCodeBarreAttribute($value): string
    {
        return $value ?: 'BIB-' . strtoupper(substr($this->id, 0, 8));
    }
}
```

**Créer :** `edugestdz/backend/app/Models/PretLivre.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class PretLivre extends Model
{
    use HasUuids;

    protected $table = 'prets_livres';

    protected $fillable = [
        'tenant_id', 'livre_id', 'emprunteur_id', 'emprunteur_type',
        'gere_par', 'date_pret', 'date_retour_prevue', 'date_retour_reelle',
        'nb_renouvellements', 'date_dernier_renouvellement',
        'statut', 'amende_calculee', 'amende_payee', 'amende_soldee',
        'sms_rappel_envoye', 'notes',
    ];

    protected $casts = [
        'date_pret'                    => 'date',
        'date_retour_prevue'           => 'date',
        'date_retour_reelle'           => 'date',
        'date_dernier_renouvellement'  => 'date',
        'amende_calculee'              => 'decimal:2',
        'amende_payee'                 => 'decimal:2',
        'amende_soldee'                => 'boolean',
        'sms_rappel_envoye'            => 'boolean',
    ];

    public function livre()
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }

    public function emprunteur()
    {
        // Dynamique selon le type
        return $this->emprunteur_type === 'eleve'
            ? $this->belongsTo(Eleve::class, 'emprunteur_id')
            : $this->belongsTo(User::class, 'emprunteur_id');
    }

    public function gerePar()
    {
        return $this->belongsTo(User::class, 'gere_par');
    }

    public function amendes()
    {
        return $this->hasMany(AmendesBibliotheque::class, 'pret_id');
    }

    // Calculer les jours de retard
    public function getNbJoursRetardAttribute(): int
    {
        if ($this->statut === 'rendu' && $this->date_retour_reelle) {
            return max(0, $this->date_retour_prevue->diffInDays($this->date_retour_reelle, false));
        }
        if (in_array($this->statut, ['en_cours', 'en_retard', 'renouvele'])) {
            return max(0, $this->date_retour_prevue->diffInDays(now(), false));
        }
        return 0;
    }

    public function estEnRetard(): bool
    {
        return $this->date_retour_prevue->isPast() && !in_array($this->statut, ['rendu', 'perdu']);
    }

    public function scopeEnCours($query)
    {
        return $query->whereIn('statut', ['en_cours', 'en_retard', 'renouvele']);
    }

    public function scopeEnRetard($query)
    {
        return $query->where('date_retour_prevue', '<', now()->format('Y-m-d'))
            ->whereIn('statut', ['en_cours', 'en_retard', 'renouvele']);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/ReservationLivre.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ReservationLivre extends Model
{
    use HasUuids;

    protected $table = 'reservations_livres';

    protected $fillable = [
        'tenant_id', 'livre_id', 'reservant_id', 'reservant_type',
        'date_reservation', 'date_expiration', 'statut',
        'notification_envoyee', 'notifie_le',
    ];

    protected $casts = [
        'date_reservation'     => 'date',
        'date_expiration'      => 'date',
        'notification_envoyee' => 'boolean',
        'notifie_le'           => 'datetime',
    ];

    public function livre()
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }

    public function reservant()
    {
        return $this->belongsTo(
            $this->reservant_type === 'eleve' ? Eleve::class : User::class,
            'reservant_id'
        );
    }
}
```

**Créer :** `edugestdz/backend/app/Models/ConfigBibliotheque.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ConfigBibliotheque extends Model
{
    use HasUuids;

    protected $table = 'config_bibliotheque';

    protected $fillable = [
        'tenant_id', 'duree_pret_jours', 'max_livres_eleve', 'max_livres_enseignant',
        'max_renouvellements', 'amende_par_jour', 'frais_livre_perdu',
        'rappel_avant_jours', 'rappel_sms_actif', 'rappel_push_actif',
        'amendes_bloquent_pret', 'nom_bibliotheque', 'responsable_nom',
        'heure_ouverture', 'heure_fermeture',
    ];

    protected $casts = [
        'rappel_sms_actif'      => 'boolean',
        'rappel_push_actif'     => 'boolean',
        'amendes_bloquent_pret' => 'boolean',
        'amende_par_jour'       => 'decimal:2',
        'frais_livre_perdu'     => 'decimal:2',
    ];

    // Obtenir la config du tenant courant, créer si absente
    public static function pour(string $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'duree_pret_jours'    => 14,
                'max_livres_eleve'    => 3,
                'max_livres_enseignant'=> 5,
                'max_renouvellements' => 1,
                'amende_par_jour'     => 50,
                'frais_livre_perdu'   => 200,
                'rappel_avant_jours'  => 2,
                'rappel_sms_actif'    => true,
                'rappel_push_actif'   => true,
                'amendes_bloquent_pret'=> true,
            ]
        );
    }
}
```

**Créer :** `edugestdz/backend/app/Models/AmendesBibliotheque.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AmendesBibliotheque extends Model
{
    use HasUuids;

    protected $table = 'amendes_bibliotheque';

    protected $fillable = [
        'tenant_id', 'pret_id', 'emprunteur_id', 'montant', 'type',
        'nb_jours_retard', 'payee', 'montant_paye', 'payee_le',
        'encaissee_par', 'notes',
    ];

    protected $casts = [
        'montant'       => 'decimal:2',
        'montant_paye'  => 'decimal:2',
        'payee'         => 'boolean',
        'payee_le'      => 'datetime',
    ];

    public function pret()
    {
        return $this->belongsTo(PretLivre::class, 'pret_id');
    }
}
```

---

## ÉTAPE 3 — BibliothequeService

**Créer :** `edugestdz/backend/app/Services/BibliothequeService.php`

```php
<?php

namespace App\Services;

use App\Models\Livre;
use App\Models\PretLivre;
use App\Models\ReservationLivre;
use App\Models\ConfigBibliotheque;
use App\Models\AmendesBibliotheque;
use App\Models\Eleve;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BibliothequeService
{
    public function __construct(
        private SmsService               $sms,
        private FirebaseService          $firebase,
        private ParentNotificationService $parentNotif,
    ) {}

    /**
     * Émettre un prêt de livre.
     */
    public function emettrePret(
        string $livreId,
        string $emprunteurId,
        string $emprunteurType,
        string $gerePar,
        ?int   $dureesJours = null
    ): PretLivre {
        $livre   = Livre::findOrFail($livreId);
        $config  = ConfigBibliotheque::pour(config('tenant.current_id'));
        $duree   = $dureesJours ?? $config->duree_pret_jours;

        // ── Vérifications ─────────────────────────────────────────────────

        // 1. Livre disponible ?
        if (!$livre->isDisponible()) {
            throw new \RuntimeException("Le livre « {$livre->titre} » n'est pas disponible. {$livre->nb_disponibles} exemplaire(s) restant(s).");
        }

        // 2. Quota atteint ?
        $maxLivres = $emprunteurType === 'enseignant'
            ? $config->max_livres_enseignant
            : $config->max_livres_eleve;

        $nbPrêtsActifs = PretLivre::where('emprunteur_id', $emprunteurId)
            ->enCours()->count();

        if ($nbPrêtsActifs >= $maxLivres) {
            throw new \RuntimeException("Quota atteint : {$nbPrêtsActifs}/{$maxLivres} livres empruntés.");
        }

        // 3. Amende impayée qui bloque ?
        if ($config->amendes_bloquent_pret) {
            $amendesImpayees = AmendesBibliotheque::where('emprunteur_id', $emprunteurId)
                ->where('payee', false)->sum('montant');
            if ($amendesImpayees > 0) {
                throw new \RuntimeException("Prêt bloqué : {$amendesImpayees} DA d'amende(s) impayée(s).");
            }
        }

        // ── Créer le prêt ─────────────────────────────────────────────────
        $pret = DB::transaction(function () use ($livre, $emprunteurId, $emprunteurType, $gerePar, $duree) {
            $pret = PretLivre::create([
                'tenant_id'          => config('tenant.current_id'),
                'livre_id'           => $livre->id,
                'emprunteur_id'      => $emprunteurId,
                'emprunteur_type'    => $emprunteurType,
                'gere_par'           => $gerePar,
                'date_pret'          => today(),
                'date_retour_prevue' => today()->addDays($duree),
                'statut'             => 'en_cours',
            ]);

            // Décrémenter le stock
            $livre->decrement('nb_disponibles');

            return $pret;
        });

        // ── Notifier le parent si c'est un élève ──────────────────────────
        if ($emprunteurType === 'eleve') {
            $eleve = Eleve::find($emprunteurId);
            if ($eleve) {
                $this->parentNotif->notifier(
                    $eleve->id,
                    'autre',
                    "📚 Livre emprunté — {$livre->titre}",
                    "Votre enfant {$eleve->prenom} a emprunté « {$livre->titre} ». Retour prévu le {$pret->date_retour_prevue->format('d/m/Y')}.",
                    ['livre_id' => $livre->id, 'pret_id' => $pret->id]
                );
            }
        }

        Log::info("Prêt émis: livre {$livre->id} → emprunteur {$emprunteurId}");
        return $pret->load('livre', 'gerePar');
    }

    /**
     * Enregistrer le retour d'un livre.
     */
    public function enregistrerRetour(string $pretId, string $gerePar): array
    {
        $pret   = PretLivre::with('livre')->findOrFail($pretId);
        $config = ConfigBibliotheque::pour(config('tenant.current_id'));

        if (in_array($pret->statut, ['rendu', 'perdu'])) {
            throw new \RuntimeException("Ce prêt est déjà clôturé (statut: {$pret->statut}).");
        }

        $nbJoursRetard = $pret->nb_jours_retard;
        $amende        = 0;

        DB::transaction(function () use ($pret, $config, $nbJoursRetard, &$amende, $gerePar) {
            // Calcul amende si retard
            if ($nbJoursRetard > 0) {
                $amende = round($nbJoursRetard * $config->amende_par_jour, 2);

                AmendesBibliotheque::create([
                    'tenant_id'      => $pret->tenant_id,
                    'pret_id'        => $pret->id,
                    'emprunteur_id'  => $pret->emprunteur_id,
                    'montant'        => $amende,
                    'type'           => 'retard',
                    'nb_jours_retard'=> $nbJoursRetard,
                ]);
            }

            // Mettre à jour le prêt
            $pret->update([
                'statut'              => 'rendu',
                'date_retour_reelle'  => today(),
                'amende_calculee'     => $amende,
            ]);

            // Réincrémenter le stock
            $pret->livre->increment('nb_disponibles');

            // Vérifier les réservations en attente pour ce livre
            $this->notifierProchainReservant($pret->livre_id);
        });

        return [
            'pret'          => $pret->fresh('livre'),
            'jours_retard'  => $nbJoursRetard,
            'amende'        => $amende,
            'message'       => $nbJoursRetard > 0
                ? "Retour avec {$nbJoursRetard} jour(s) de retard. Amende : {$amende} DA."
                : "Retour dans les délais. Aucune amende.",
        ];
    }

    /**
     * Renouveler un prêt.
     */
    public function renouvelerPret(string $pretId): PretLivre
    {
        $pret   = PretLivre::findOrFail($pretId);
        $config = ConfigBibliotheque::pour(config('tenant.current_id'));

        if ($pret->nb_renouvellements >= $config->max_renouvellements) {
            throw new \RuntimeException("Maximum {$config->max_renouvellements} renouvellement(s) autorisé(s).");
        }

        if ($pret->estEnRetard()) {
            throw new \RuntimeException("Impossible de renouveler : le livre est en retard.");
        }

        // Vérifier s'il n'y a pas de réservation en attente
        $reservation = ReservationLivre::where('livre_id', $pret->livre_id)
            ->where('statut', 'en_attente')->exists();
        if ($reservation) {
            throw new \RuntimeException("Impossible de renouveler : ce livre est réservé par un autre lecteur.");
        }

        $pret->update([
            'date_retour_prevue'          => $pret->date_retour_prevue->addDays($config->duree_pret_jours),
            'nb_renouvellements'          => $pret->nb_renouvellements + 1,
            'date_dernier_renouvellement' => today(),
            'statut'                      => 'renouvele',
        ]);

        return $pret->fresh('livre');
    }

    /**
     * Déclarer un livre perdu.
     */
    public function declarerPerdu(string $pretId, string $gerePar): array
    {
        $pret   = PretLivre::with('livre')->findOrFail($pretId);
        $config = ConfigBibliotheque::pour(config('tenant.current_id'));

        $fraisPerte = $pret->livre->prix_remplacement + $config->frais_livre_perdu;

        DB::transaction(function () use ($pret, $fraisPerte) {
            AmendesBibliotheque::create([
                'tenant_id'      => $pret->tenant_id,
                'pret_id'        => $pret->id,
                'emprunteur_id'  => $pret->emprunteur_id,
                'montant'        => $fraisPerte,
                'type'           => 'perte',
            ]);

            $pret->update([
                'statut'          => 'perdu',
                'amende_calculee' => $fraisPerte,
            ]);

            // Ne pas remettre en stock (livre perdu)
            // Décrémenter nb_exemplaires
            $pret->livre->decrement('nb_exemplaires');
        });

        return [
            'pret'        => $pret->fresh(),
            'frais_perte' => $fraisPerte,
            'message'     => "Livre déclaré perdu. Frais : {$fraisPerte} DA (remplacement + dossier).",
        ];
    }

    /**
     * Réserver un livre (pour quand il sera disponible).
     */
    public function reserverLivre(string $livreId, string $reservantId, string $type = 'eleve'): ReservationLivre
    {
        $livre = Livre::findOrFail($livreId);

        // Vérifier qu'il n'y a pas déjà une réservation active
        $existe = ReservationLivre::where('livre_id', $livreId)
            ->where('reservant_id', $reservantId)
            ->whereIn('statut', ['en_attente', 'disponible'])
            ->exists();

        if ($existe) {
            throw new \RuntimeException("Vous avez déjà une réservation active pour ce livre.");
        }

        // Si disponible, émettre directement un prêt plutôt que réserver
        if ($livre->isDisponible()) {
            throw new \RuntimeException("Ce livre est disponible. Venez le prendre directement à la bibliothèque.");
        }

        $reservation = ReservationLivre::create([
            'tenant_id'        => config('tenant.current_id'),
            'livre_id'         => $livreId,
            'reservant_id'     => $reservantId,
            'reservant_type'   => $type,
            'date_reservation' => today(),
            'date_expiration'  => today()->addDays(30), // réservation valable 30 jours
            'statut'           => 'en_attente',
        ]);

        return $reservation->load('livre');
    }

    /**
     * Notifier le prochain réservant quand un livre devient disponible.
     */
    private function notifierProchainReservant(string $livreId): void
    {
        $reservation = ReservationLivre::where('livre_id', $livreId)
            ->where('statut', 'en_attente')
            ->oldest('created_at')
            ->first();

        if (!$reservation) return;

        $livre = Livre::find($livreId);

        // Marquer comme disponible pour ce réservant
        $reservation->update([
            'statut'                => 'disponible',
            'notification_envoyee'  => true,
            'notifie_le'            => now(),
            'date_expiration'       => today()->addDays(3), // 3 jours pour venir le récupérer
        ]);

        // Notifier
        if ($reservation->reservant_type === 'eleve') {
            $eleve = Eleve::find($reservation->reservant_id);
            if ($eleve) {
                $this->parentNotif->notifier(
                    $eleve->id,
                    'autre',
                    "📚 Livre disponible — {$livre->titre}",
                    "Le livre « {$livre->titre} » réservé par {$eleve->prenom} est maintenant disponible. À récupérer dans les 3 jours.",
                    ['livre_id' => $livreId]
                );
            }
        }
    }

    /**
     * Envoyer rappels de retour (utilisé par le scheduler).
     */
    public function envoyerRappels(): int
    {
        $config     = ConfigBibliotheque::pour(config('tenant.current_id'));
        $dateLimit  = now()->addDays($config->rappel_avant_jours)->format('Y-m-d');

        $pretsARappeler = PretLivre::with(['livre', 'emprunteur'])
            ->whereDate('date_retour_prevue', $dateLimit)
            ->whereIn('statut', ['en_cours', 'renouvele'])
            ->where('sms_rappel_envoye', false)
            ->get();

        $envoyes = 0;

        foreach ($pretsARappeler as $pret) {
            $livre = $pret->livre;
            $msg   = "📚 EduGest Bibliothèque : Le livre « {$livre->titre} » doit être rendu le {$pret->date_retour_prevue->format('d/m/Y')}. Amende en cas de retard : {$config->amende_par_jour} DA/jour.";

            try {
                if ($pret->emprunteur_type === 'eleve') {
                    $this->parentNotif->notifier(
                        $pret->emprunteur_id,
                        'autre',
                        "📚 Rappel retour livre",
                        "« {$livre->titre} » doit être rendu le {$pret->date_retour_prevue->format('d/m/Y')}.",
                        ['type' => 'bibliotheque', 'pret_id' => $pret->id],
                        $config->rappel_sms_actif
                    );
                }
                $pret->update(['sms_rappel_envoye' => true]);
                $envoyes++;
            } catch (\Throwable $e) {
                Log::warning("Rappel bibliothèque échoué pret {$pret->id}: " . $e->getMessage());
            }
        }

        // Aussi mettre à jour les prêts en retard
        PretLivre::whereDate('date_retour_prevue', '<', today()->format('Y-m-d'))
            ->where('statut', 'en_cours')
            ->update(['statut' => 'en_retard']);

        return $envoyes;
    }

    /**
     * Dashboard bibliothèque.
     */
    public function getDashboard(): array
    {
        $tenantId = config('tenant.current_id');

        return [
            'total_livres'       => Livre::where('tenant_id', $tenantId)->actif()->count(),
            'livres_disponibles' => Livre::where('tenant_id', $tenantId)->disponible()->count(),
            'prets_en_cours'     => PretLivre::where('tenant_id', $tenantId)->enCours()->count(),
            'prets_en_retard'    => PretLivre::where('tenant_id', $tenantId)->enRetard()->count(),
            'reservations'       => \App\Models\ReservationLivre::where('tenant_id', $tenantId)
                ->where('statut', 'en_attente')->count(),
            'amendes_impayees'   => AmendesBibliotheque::where('tenant_id', $tenantId)
                ->where('payee', false)->sum('montant'),
            'top_emprunteurs'    => PretLivre::where('tenant_id', $tenantId)
                ->whereMonth('date_pret', now()->month)
                ->selectRaw('emprunteur_id, COUNT(*) as total')
                ->groupBy('emprunteur_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'livres_populaires'  => PretLivre::where('tenant_id', $tenantId)
                ->selectRaw('livre_id, COUNT(*) as total')
                ->groupBy('livre_id')
                ->orderByDesc('total')
                ->limit(5)
                ->with('livre:id,titre,auteur')
                ->get(),
        ];
    }
}
```

---

## ÉTAPE 4 — BibliothequeController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/BibliothequeController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Livre;
use App\Models\PretLivre;
use App\Models\ReservationLivre;
use App\Models\ConfigBibliotheque;
use App\Models\AmendesBibliotheque;
use App\Services\BibliothequeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BibliothequeController extends Controller
{
    public function __construct(private BibliothequeService $service) {}

    // ════════════════════════════════════════════════════════
    // CATALOGUE
    // ════════════════════════════════════════════════════════

    /** @OA\Get(path="/api/v1/bibliotheque/livres", summary="Liste du catalogue", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function indexLivres(Request $request): JsonResponse
    {
        $livres = Livre::actif()
            ->when($request->filled('search'), fn($q) =>
                $q->where('titre', 'ilike', "%{$request->search}%")
                  ->orWhere('auteur', 'ilike', "%{$request->search}%")
                  ->orWhere('isbn', 'like', "%{$request->search}%")
            )
            ->when($request->filled('categorie'),  fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('disponible'), fn($q) => $q->where('nb_disponibles', '>', 0))
            ->when($request->filled('langue'),     fn($q) => $q->where('langue', $request->langue))
            ->withCount(['pretsEnCours as nb_prets_en_cours'])
            ->orderBy('titre')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $livres,
            'stats'   => [
                'total'       => Livre::actif()->count(),
                'disponibles' => Livre::disponible()->count(),
                'categories'  => Livre::CATEGORIES,
            ],
        ]);
    }

    /** @OA\Post(path="/api/v1/bibliotheque/livres", summary="Ajouter un livre au catalogue", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function storeLivre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'              => 'required|string|max:300',
            'auteur'             => 'nullable|string|max:200',
            'editeur'            => 'nullable|string|max:200',
            'isbn'               => 'nullable|string|max:20',
            'annee_edition'      => 'nullable|integer|min:1900|max:2030',
            'categorie'          => 'required|in:' . implode(',', array_keys(Livre::CATEGORIES)),
            'niveau_scolaire'    => 'nullable|string|max:20',
            'matiere'            => 'nullable|string|max:100',
            'langue'             => 'in:ar,fr,en',
            'nb_exemplaires'     => 'integer|min:1|max:999',
            'rayon'              => 'nullable|string|max:50',
            'cote'               => 'nullable|string|max:50',
            'code_barre'         => 'nullable|string|max:50|unique:livres,code_barre',
            'prix_remplacement'  => 'nullable|numeric|min:0',
            'description'        => 'nullable|string|max:1000',
        ]);

        $exemplaires = $validated['nb_exemplaires'] ?? 1;
        $livre = Livre::create([
            ...$validated,
            'tenant_id'      => config('tenant.current_id'),
            'nb_exemplaires' => $exemplaires,
            'nb_disponibles' => $exemplaires,
        ]);

        return response()->json(['success' => true, 'data' => $livre, 'message' => 'Livre ajouté au catalogue'], 201);
    }

    /** @OA\Get(path="/api/v1/bibliotheque/livres/{id}", summary="Détail d'un livre", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function showLivre(string $id): JsonResponse
    {
        $livre = Livre::withCount(['pretsEnCours', 'reservations'])
            ->with(['pretsEnCours' => fn($q) => $q->limit(5)])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $livre]);
    }

    /** @OA\Put(path="/api/v1/bibliotheque/livres/{id}", summary="Modifier un livre", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function updateLivre(Request $request, string $id): JsonResponse
    {
        $livre = Livre::findOrFail($id);
        $livre->update($request->only([
            'titre', 'auteur', 'editeur', 'isbn', 'annee_edition',
            'categorie', 'niveau_scolaire', 'matiere', 'langue',
            'rayon', 'cote', 'prix_remplacement', 'description', 'actif',
        ]));
        return response()->json(['success' => true, 'data' => $livre->fresh()]);
    }

    // ════════════════════════════════════════════════════════
    // PRÊTS
    // ════════════════════════════════════════════════════════

    /** @OA\Get(path="/api/v1/bibliotheque/prets", summary="Liste des prêts", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function indexPrets(Request $request): JsonResponse
    {
        $prets = PretLivre::with(['livre:id,titre,auteur,categorie'])
            ->when($request->filled('statut'),       fn($q) => $q->where('statut', $request->statut))
            ->when($request->filled('emprunteur_id'),fn($q) => $q->where('emprunteur_id', $request->emprunteur_id))
            ->when($request->filled('en_retard'),    fn($q) => $q->enRetard())
            ->orderByDesc('date_pret')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $prets]);
    }

    /** @OA\Post(path="/api/v1/bibliotheque/prets", summary="Émettre un prêt", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function emettrePret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'livre_id'        => 'required|uuid|exists:livres,id',
            'emprunteur_id'   => 'required|uuid',
            'emprunteur_type' => 'in:eleve,enseignant,personnel',
            'duree_jours'     => 'nullable|integer|min:1|max:90',
        ]);

        try {
            $pret = $this->service->emettrePret(
                $validated['livre_id'],
                $validated['emprunteur_id'],
                $validated['emprunteur_type'] ?? 'eleve',
                auth('api')->id(),
                $validated['duree_jours'] ?? null
            );
            return response()->json(['success' => true, 'data' => $pret, 'message' => 'Prêt émis'], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** @OA\Post(path="/api/v1/bibliotheque/prets/{id}/retour", summary="Enregistrer le retour", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function retourLivre(string $id): JsonResponse
    {
        try {
            $result = $this->service->enregistrerRetour($id, auth('api')->id());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** @OA\Post(path="/api/v1/bibliotheque/prets/{id}/renouveler", summary="Renouveler un prêt", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function renouvelerPret(string $id): JsonResponse
    {
        try {
            $pret = $this->service->renouvelerPret($id);
            return response()->json(['success' => true, 'data' => $pret, 'message' => 'Prêt renouvelé']);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** @OA\Post(path="/api/v1/bibliotheque/prets/{id}/perdu", summary="Déclarer un livre perdu", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function declarerPerdu(string $id): JsonResponse
    {
        try {
            $result = $this->service->declarerPerdu($id, auth('api')->id());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════
    // RÉSERVATIONS
    // ════════════════════════════════════════════════════════

    /** @OA\Post(path="/api/v1/bibliotheque/reservations", summary="Réserver un livre", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function reserverLivre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'livre_id'       => 'required|uuid|exists:livres,id',
            'reservant_id'   => 'required|uuid',
            'reservant_type' => 'in:eleve,enseignant,personnel',
        ]);

        try {
            $reservation = $this->service->reserverLivre(
                $validated['livre_id'],
                $validated['reservant_id'],
                $validated['reservant_type'] ?? 'eleve'
            );
            return response()->json(['success' => true, 'data' => $reservation, 'message' => 'Réservation enregistrée'], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** @OA\Get(path="/api/v1/bibliotheque/reservations", summary="Liste des réservations", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function indexReservations(Request $request): JsonResponse
    {
        $reservations = ReservationLivre::with(['livre:id,titre,auteur'])
            ->when($request->filled('statut'), fn($q) => $q->where('statut', $request->statut))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    // ════════════════════════════════════════════════════════
    // AMENDES
    // ════════════════════════════════════════════════════════

    /** @OA\Post(path="/api/v1/bibliotheque/amendes/{id}/payer", summary="Encaisser une amende", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function payerAmende(Request $request, string $id): JsonResponse
    {
        $amende   = AmendesBibliotheque::findOrFail($id);
        $validated = $request->validate([
            'montant_paye' => 'required|numeric|min:0|max:' . $amende->montant,
        ]);

        $amende->update([
            'montant_paye'   => $validated['montant_paye'],
            'payee'          => $validated['montant_paye'] >= $amende->montant,
            'payee_le'       => now(),
            'encaissee_par'  => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $amende->fresh(),
            'message' => $amende->payee ? 'Amende soldée' : 'Paiement partiel enregistré',
        ]);
    }

    /** @OA\Get(path="/api/v1/bibliotheque/amendes", summary="Liste des amendes", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function indexAmendes(Request $request): JsonResponse
    {
        $amendes = AmendesBibliotheque::with(['pret.livre:id,titre'])
            ->when($request->filled('payee'),        fn($q) => $q->where('payee', (bool) $request->payee))
            ->when($request->filled('emprunteur_id'),fn($q) => $q->where('emprunteur_id', $request->emprunteur_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success'        => true,
            'data'           => $amendes,
            'total_impayes'  => AmendesBibliotheque::where('payee', false)->sum('montant'),
        ]);
    }

    // ════════════════════════════════════════════════════════
    // DASHBOARD & CONFIG
    // ════════════════════════════════════════════════════════

    /** @OA\Get(path="/api/v1/bibliotheque/dashboard", summary="Dashboard bibliothèque", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->getDashboard()]);
    }

    /** @OA\Get(path="/api/v1/bibliotheque/config", summary="Configuration bibliothèque", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function getConfig(): JsonResponse
    {
        $config = ConfigBibliotheque::pour(config('tenant.current_id'));
        return response()->json(['success' => true, 'data' => $config]);
    }

    /** @OA\Put(path="/api/v1/bibliotheque/config", summary="Modifier la configuration", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'duree_pret_jours'      => 'integer|min:1|max:90',
            'max_livres_eleve'      => 'integer|min:1|max:20',
            'max_livres_enseignant' => 'integer|min:1|max:20',
            'max_renouvellements'   => 'integer|min:0|max:5',
            'amende_par_jour'       => 'numeric|min:0|max:5000',
            'frais_livre_perdu'     => 'numeric|min:0',
            'rappel_avant_jours'    => 'integer|min:1|max:7',
            'rappel_sms_actif'      => 'boolean',
            'rappel_push_actif'     => 'boolean',
            'amendes_bloquent_pret' => 'boolean',
            'nom_bibliotheque'      => 'nullable|string|max:200',
            'responsable_nom'       => 'nullable|string|max:200',
            'heure_ouverture'       => 'nullable|date_format:H:i',
            'heure_fermeture'       => 'nullable|date_format:H:i',
        ]);

        $config = ConfigBibliotheque::pour(config('tenant.current_id'));
        $config->update($validated);

        return response()->json(['success' => true, 'data' => $config, 'message' => 'Configuration mise à jour']);
    }

    /** @OA\Post(path="/api/v1/bibliotheque/import", summary="Import catalogue CSV", tags={"Bibliothèque"}, security={{"bearerAuth":{}}}) */
    public function importCatalogue(Request $request): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|mimes:csv,txt|max:5120']);

        $fichier = $request->file('fichier');
        $lignes  = array_map('str_getcsv', file($fichier->getPathname()));
        $entetes = array_shift($lignes);
        $importes = 0;
        $erreurs  = [];

        foreach ($lignes as $i => $ligne) {
            try {
                $data = array_combine($entetes, $ligne);
                Livre::create([
                    'tenant_id'      => config('tenant.current_id'),
                    'titre'          => $data['titre'] ?? 'Inconnu',
                    'auteur'         => $data['auteur'] ?? null,
                    'isbn'           => $data['isbn'] ?? null,
                    'categorie'      => $data['categorie'] ?? 'autre',
                    'nb_exemplaires' => (int) ($data['nb_exemplaires'] ?? 1),
                    'nb_disponibles' => (int) ($data['nb_exemplaires'] ?? 1),
                ]);
                $importes++;
            } catch (\Throwable $e) {
                $erreurs[] = "Ligne " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success'  => true,
            'importes' => $importes,
            'erreurs'  => $erreurs,
            'message'  => "{$importes} livre(s) importé(s)" . (count($erreurs) > 0 ? " avec " . count($erreurs) . " erreur(s)" : ""),
        ]);
    }
}
```

---

## ÉTAPE 5 — Commande scheduler : rappels et mise à jour retards

**Créer :** `edugestdz/backend/app/Console/Commands/BibliothequeRappelsCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\BibliothequeService;
use Illuminate\Console\Command;

class BibliothequeRappelsCommand extends Command
{
    protected $signature   = 'edugest:bibliotheque-rappels';
    protected $description = 'Envoyer rappels retour bibliothèque + mettre à jour les retards';

    public function handle(BibliothequeService $service): int
    {
        $envoyes = $service->envoyerRappels();
        $this->info("✅ {$envoyes} rappel(s) bibliothèque envoyé(s)");
        return Command::SUCCESS;
    }
}
```

**Modifier :** `edugestdz/backend/app/Console/Kernel.php`

Ajouter dans `schedule()` :
```php
// Rappels bibliothèque — chaque matin à 7h30
$schedule->command('edugest:bibliotheque-rappels')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();
```

---

## ÉTAPE 6 — Routes API

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\BibliothequeController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/bibliotheque')->group(function () {
    // Dashboard & Config
    Route::get('/dashboard',                [BibliothequeController::class, 'dashboard']);
    Route::get('/config',                   [BibliothequeController::class, 'getConfig']);
    Route::put('/config',                   [BibliothequeController::class, 'updateConfig']);

    // Catalogue livres
    Route::get('/livres',                   [BibliothequeController::class, 'indexLivres']);
    Route::post('/livres',                  [BibliothequeController::class, 'storeLivre']);
    Route::get('/livres/{id}',              [BibliothequeController::class, 'showLivre']);
    Route::put('/livres/{id}',              [BibliothequeController::class, 'updateLivre']);
    Route::post('/import',                  [BibliothequeController::class, 'importCatalogue']);

    // Prêts
    Route::get('/prets',                    [BibliothequeController::class, 'indexPrets']);
    Route::post('/prets',                   [BibliothequeController::class, 'emettrePret']);
    Route::post('/prets/{id}/retour',       [BibliothequeController::class, 'retourLivre']);
    Route::post('/prets/{id}/renouveler',   [BibliothequeController::class, 'renouvelerPret']);
    Route::post('/prets/{id}/perdu',        [BibliothequeController::class, 'declarerPerdu']);

    // Réservations
    Route::get('/reservations',             [BibliothequeController::class, 'indexReservations']);
    Route::post('/reservations',            [BibliothequeController::class, 'reserverLivre']);

    // Amendes
    Route::get('/amendes',                  [BibliothequeController::class, 'indexAmendes']);
    Route::post('/amendes/{id}/payer',      [BibliothequeController::class, 'payerAmende']);
});
```

---

## ÉTAPE 7 — Page React BibliothequeePage

**Créer :** `edugestdz/frontend/src/pages/BibliothequeePage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { Book, Search, Plus, ArrowLeft, ArrowRight, AlertTriangle, Clock } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  ...opts,
}).then(r => r.json());

const CATEGORIES = {
  manuel_scolaire: { label: 'Manuel scolaire', color: '#2563EB' },
  parascolaire:    { label: 'Parascolaire',    color: '#7C3AED' },
  roman:           { label: 'Roman',           color: '#10B981' },
  sciences:        { label: 'Sciences',        color: '#06B6D4' },
  islamique:       { label: 'Islamique',       color: '#F59E0B' },
  encyclopedie:    { label: 'Encyclopédie',    color: '#EF4444' },
  autre:           { label: 'Autre',           color: '#64748B' },
};

const STATUTS = {
  en_cours:  { label: 'En cours',  color: '#2563EB' },
  en_retard: { label: 'En retard', color: '#EF4444' },
  rendu:     { label: 'Rendu',     color: '#10B981' },
  perdu:     { label: 'Perdu',     color: '#64748B' },
  renouvele: { label: 'Renouvelé', color: '#F59E0B' },
};

export default function BibliothequeePage() {
  const [tab, setTab]           = useState('dashboard');
  const [dashboard, setDashboard] = useState(null);
  const [livres, setLivres]     = useState([]);
  const [prets, setPrets]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [search, setSearch]     = useState('');
  const [filtreCateg, setFiltreCateg] = useState('');
  const [filtreStatut, setFiltreStatut] = useState('');
  const [showAddLivre, setShowAddLivre] = useState(false);
  const [showAddPret, setShowAddPret]   = useState(false);
  const [form, setForm] = useState({ titre:'', auteur:'', isbn:'', categorie:'autre', nb_exemplaires:1, langue:'fr', prix_remplacement:500 });
  const [pretForm, setPretForm] = useState({ livre_id:'', emprunteur_id:'', emprunteur_type:'eleve' });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');

  useEffect(() => { loadData(); }, [tab, filtreCateg, filtreStatut]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [dash, livresRes, pretsRes] = await Promise.all([
        api('/bibliotheque/dashboard'),
        api(`/bibliotheque/livres?categorie=${filtreCateg}&per_page=20`),
        api(`/bibliotheque/prets?statut=${filtreStatut}&per_page=20`),
      ]);
      setDashboard(dash?.data);
      setLivres(livresRes?.data?.data ?? []);
      setPrets(pretsRes?.data?.data ?? []);
    } catch(e) { console.error(e); }
    finally { setLoading(false); }
  };

  const addLivre = async () => {
    setSaving(true);
    const res = await api('/bibliotheque/livres', { method:'POST', body:JSON.stringify(form) });
    setSaving(false);
    if (res.success) { setShowAddLivre(false); loadData(); setMsg('✅ Livre ajouté'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const emettrePret = async () => {
    setSaving(true);
    const res = await api('/bibliotheque/prets', { method:'POST', body:JSON.stringify(pretForm) });
    setSaving(false);
    if (res.success) { setShowAddPret(false); loadData(); setMsg('✅ Prêt émis'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 4000);
  };

  const retournerLivre = async (pretId) => {
    const res = await api(`/bibliotheque/prets/${pretId}/retour`, { method:'POST' });
    if (res.success) {
      const r = res.data;
      alert(`✅ Retour enregistré\n${r.jours_retard > 0 ? `⚠️ ${r.jours_retard} jour(s) de retard — Amende: ${r.amende} DA` : 'Dans les délais ✓'}`);
      loadData();
    } else alert('❌ ' + res.message);
  };

  const renouvelerPret = async (pretId) => {
    const res = await api(`/bibliotheque/prets/${pretId}/renouveler`, { method:'POST' });
    if (res.success) { loadData(); setMsg('✅ Prêt renouvelé'); }
    else alert('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const StatBox = ({ label, value, color, icon }) => (
    <div style={{ background:'#0D1117', border:`1px solid #1E2D40`, borderTop:`2px solid ${color}`, borderRadius:'14px', padding:'18px 20px' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'12px' }}>
        <div style={{ fontSize:'10px', fontWeight:700, color:'#64748B', textTransform:'uppercase', letterSpacing:'1px' }}>{label}</div>
        <span style={{ fontSize:'20px' }}>{icon}</span>
      </div>
      <div style={{ fontSize:'26px', fontWeight:900, color:'#fff' }}>{loading ? '...' : (value ?? 0)}</div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#070B14', minHeight:'100vh' }}>
      {/* Header */}
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff', display:'flex', alignItems:'center', gap:'10px' }}>
            📚 Bibliothèque Scolaire
          </h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>Catalogue · Prêts · Retours · Amendes</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <button onClick={() => setShowAddPret(true)} style={{ background:'#161C26', border:'1px solid #1E2D40', color:'#E2E8F0', borderRadius:'9px', padding:'9px 16px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
            📤 Émettre prêt
          </button>
          <button onClick={() => setShowAddLivre(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'9px 16px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
            + Ajouter livre
          </button>
        </div>
      </div>

      {msg && (
        <div style={{ background: msg.includes('✅') ? '#0d2515' : '#450a0a', border:`1px solid ${msg.includes('✅') ? '#16a34a' : '#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color: msg.includes('✅') ? '#4ade80' : '#f87171' }}>
          {msg}
        </div>
      )}

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['dashboard','📊 Dashboard'],['catalogue','📚 Catalogue'],['prets','📤 Prêts'],['retards','⚠️ En retard'],['amendes','💰 Amendes']].map(([id,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748B',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1E2D40'}`,
            borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer',
          }}>{label}</button>
        ))}
      </div>

      {/* Dashboard */}
      {tab === 'dashboard' && (
        <div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(6,1fr)', gap:'12px', marginBottom:'24px' }}>
            <StatBox label="Total livres"     value={dashboard?.total_livres}       color="#2563EB" icon="📚" />
            <StatBox label="Disponibles"      value={dashboard?.livres_disponibles}  color="#10B981" icon="✅" />
            <StatBox label="Prêts en cours"   value={dashboard?.prets_en_cours}      color="#F59E0B" icon="📤" />
            <StatBox label="En retard"        value={dashboard?.prets_en_retard}     color="#EF4444" icon="⚠️" />
            <StatBox label="Réservations"     value={dashboard?.reservations}        color="#7C3AED" icon="🔖" />
            <StatBox label="Amendes (DA)"     value={dashboard?.amendes_impayees}    color="#EF4444" icon="💰" />
          </div>
          {dashboard?.prets_en_retard > 0 && (
            <div style={{ background:'#450a0a', border:'1px solid #b91c1c', borderRadius:'10px', padding:'14px 18px', marginBottom:'16px', display:'flex', alignItems:'center', gap:'12px' }}>
              <span style={{ fontSize:'20px' }}>🚨</span>
              <div>
                <div style={{ fontSize:'13px', fontWeight:800, color:'#f87171' }}>{dashboard.prets_en_retard} livre(s) en retard</div>
                <div style={{ fontSize:'11px', color:'#64748B' }}>Des amendes s'accumulent — envoyer des rappels</div>
              </div>
              <button onClick={() => api('/bibliotheque-rappels', { method:'POST' }).then(() => setMsg('✅ Rappels envoyés'))} style={{ marginLeft:'auto', background:'#b91c1c', color:'#fff', border:'none', borderRadius:'7px', padding:'7px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                📱 Envoyer rappels
              </button>
            </div>
          )}
        </div>
      )}

      {/* Catalogue */}
      {tab === 'catalogue' && (
        <div>
          <div style={{ display:'flex', gap:'10px', marginBottom:'16px', flexWrap:'wrap' }}>
            <div style={{ display:'flex', alignItems:'center', gap:'8px', background:'#111318', border:'1px solid #1E2D40', borderRadius:'9px', padding:'9px 14px', flex:1, minWidth:'200px' }}>
              🔍 <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Titre, auteur, ISBN..."
                style={{ background:'none', border:'none', color:'#E2E8F0', fontSize:'12px', outline:'none', width:'100%', fontFamily:'Inter,sans-serif' }} />
            </div>
            <select value={filtreCateg} onChange={e => setFiltreCateg(e.target.value)}
              style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'9px', color:'#E2E8F0', padding:'9px 14px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
              <option value="">Toutes catégories</option>
              {Object.entries(CATEGORIES).map(([k,v]) => <option key={k} value={k}>{v.label}</option>)}
            </select>
            <select style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'9px', color:'#E2E8F0', padding:'9px 14px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
              <option>Toutes langues</option>
              <option value="ar">العربية</option>
              <option value="fr">Français</option>
              <option value="en">English</option>
            </select>
          </div>

          <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'10px' }}>
            {loading ? (
              <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>Chargement...</div>
            ) : livres.filter(l => !search || l.titre.toLowerCase().includes(search.toLowerCase()) || (l.auteur||'').toLowerCase().includes(search.toLowerCase())).map(livre => (
              <div key={livre.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'12px', padding:'16px', borderTop:`2px solid ${CATEGORIES[livre.categorie]?.color ?? '#64748B'}` }}>
                <div style={{ fontSize:'12px', fontWeight:800, color:'#fff', marginBottom:'4px', lineHeight:1.3 }}>{livre.titre}</div>
                {livre.auteur && <div style={{ fontSize:'10px', color:'#64748B', marginBottom:'8px' }}>✍️ {livre.auteur}</div>}
                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'8px' }}>
                  <span style={{ fontSize:'9px', background:(CATEGORIES[livre.categorie]?.color ?? '#64748B') + '22', color: CATEGORIES[livre.categorie]?.color ?? '#64748B', padding:'2px 8px', borderRadius:'20px', fontWeight:700 }}>
                    {CATEGORIES[livre.categorie]?.label ?? livre.categorie}
                  </span>
                  <span style={{ fontSize:'10px', fontWeight:800, color: livre.nb_disponibles > 0 ? '#10B981' : '#EF4444' }}>
                    {livre.nb_disponibles}/{livre.nb_exemplaires} dispo
                  </span>
                </div>
                {livre.rayon && <div style={{ fontSize:'9px', color:'#475569' }}>📍 {livre.rayon}</div>}
                <div style={{ display:'flex', gap:'6px', marginTop:'10px' }}>
                  {livre.nb_disponibles > 0 ? (
                    <button onClick={() => { setPretForm(f => ({...f, livre_id: livre.id})); setShowAddPret(true); }}
                      style={{ flex:1, background:'#2563EB', color:'#fff', border:'none', borderRadius:'7px', padding:'6px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                      Prêter
                    </button>
                  ) : (
                    <button onClick={() => api('/bibliotheque/reservations', { method:'POST', body:JSON.stringify({ livre_id:livre.id, reservant_id:'', reservant_type:'eleve' }) })}
                      style={{ flex:1, background:'#7C3AED22', border:'1px solid #7C3AED44', color:'#a78bfa', borderRadius:'7px', padding:'6px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                      Réserver
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Prêts */}
      {(tab === 'prets' || tab === 'retards') && (
        <div>
          {tab === 'retards' && (
            <div style={{ background:'#1f1008', border:'1px solid #c2410c', borderRadius:'9px', padding:'12px 16px', marginBottom:'14px', fontSize:'11px', color:'#fb923c' }}>
              ⚠️ Les livres en retard accumulent des amendes de <strong>50 DA/jour</strong>. Contactez les emprunteurs.
            </div>
          )}
          <div style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
            <table style={{ width:'100%', borderCollapse:'collapse' }}>
              <thead>
                <tr style={{ background:'#161C26' }}>
                  {['Livre','Emprunteur','Date prêt','Retour prévu','Retard','Statut','Actions'].map(h => (
                    <th key={h} style={{ padding:'10px 14px', fontSize:'10px', fontWeight:700, color:'#64748B', textTransform:'uppercase', letterSpacing:'1px', borderBottom:'1px solid #1E2D40', textAlign:'left' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={7} style={{ padding:'40px', textAlign:'center', color:'#64748B' }}>Chargement...</td></tr>
                ) : prets.filter(p => tab !== 'retards' || p.statut === 'en_retard').map(pret => {
                  const statut = STATUTS[pret.statut] ?? STATUTS.en_cours;
                  const joursRetard = pret.statut === 'en_retard'
                    ? Math.floor((new Date() - new Date(pret.date_retour_prevue)) / 86400000)
                    : 0;
                  return (
                    <tr key={pret.id} style={{ background: pret.statut === 'en_retard' ? '#ef444408' : 'transparent' }}
                      onMouseEnter={e => e.currentTarget.style.background = '#161C2644'}
                      onMouseLeave={e => e.currentTarget.style.background = pret.statut === 'en_retard' ? '#ef444408' : 'transparent'}>
                      <td style={{ padding:'11px 14px', fontSize:'12px', borderBottom:'1px solid #1E2D4044' }}>
                        <div style={{ fontWeight:700, color:'#f1f5f9' }}>{pret.livre?.titre ?? '—'}</div>
                        <div style={{ fontSize:'10px', color:'#64748B' }}>{pret.livre?.auteur}</div>
                      </td>
                      <td style={{ padding:'11px 14px', fontSize:'11px', color:'#E2E8F0', borderBottom:'1px solid #1E2D4044' }}>
                        {pret.emprunteur_type === 'eleve' ? '👦' : '👨‍🏫'} {pret.emprunteur_id?.slice(0,8)}
                      </td>
                      <td style={{ padding:'11px 14px', fontSize:'11px', color:'#64748B', borderBottom:'1px solid #1E2D4044' }}>
                        {pret.date_pret}
                      </td>
                      <td style={{ padding:'11px 14px', fontSize:'11px', fontWeight:700, borderBottom:'1px solid #1E2D4044', color: pret.statut === 'en_retard' ? '#EF4444' : '#E2E8F0' }}>
                        {pret.date_retour_prevue}
                      </td>
                      <td style={{ padding:'11px 14px', fontSize:'11px', borderBottom:'1px solid #1E2D4044', color: joursRetard > 0 ? '#EF4444' : '#10B981', fontWeight:joursRetard > 0 ? 800 : 400 }}>
                        {joursRetard > 0 ? `+${joursRetard}j (${joursRetard * 50} DA)` : '—'}
                      </td>
                      <td style={{ padding:'11px 14px', borderBottom:'1px solid #1E2D4044' }}>
                        <span style={{ background:statut.color+'22', color:statut.color, fontSize:'10px', fontWeight:700, padding:'2px 9px', borderRadius:'20px' }}>{statut.label}</span>
                      </td>
                      <td style={{ padding:'11px 14px', borderBottom:'1px solid #1E2D4044' }}>
                        <div style={{ display:'flex', gap:'4px' }}>
                          {['en_cours','en_retard','renouvele'].includes(pret.statut) && (
                            <button onClick={() => retournerLivre(pret.id)} style={{ background:'#10B98122', border:'1px solid #10B98144', color:'#10B981', borderRadius:'6px', padding:'5px 10px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                              ↩ Retour
                            </button>
                          )}
                          {pret.statut === 'en_cours' && (
                            <button onClick={() => renouvelerPret(pret.id)} style={{ background:'#2563EB22', border:'1px solid #2563EB44', color:'#93C5FD', borderRadius:'6px', padding:'5px 10px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                              🔄
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Modal ajouter livre */}
      {showAddLivre && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowAddLivre(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'500px', maxWidth:'90%' }} onClick={e => e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📚 Ajouter un livre</h3>
            {[
              ['Titre *', 'titre', 'text', 'ex: Mathématiques 3AS'],
              ['Auteur', 'auteur', 'text', 'ex: Mohamed Benali'],
              ['ISBN', 'isbn', 'text', 'ex: 978-9947-XX-XXX-X'],
              ['Prix de remplacement (DA)', 'prix_remplacement', 'number', '500'],
              ['Nb exemplaires', 'nb_exemplaires', 'number', '1'],
            ].map(([label, key, type, placeholder]) => (
              <div key={key} style={{ marginBottom:'10px' }}>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                <input type={type} value={form[key]} onChange={e => setForm(f => ({...f, [key]: e.target.value}))}
                  placeholder={placeholder}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            ))}
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'10px' }}>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Catégorie *</label>
                <select value={form.categorie} onChange={e => setForm(f => ({...f, categorie:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                  {Object.entries(CATEGORIES).map(([k,v]) => <option key={k} value={k}>{v.label}</option>)}
                </select>
              </div>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Langue</label>
                <select value={form.langue} onChange={e => setForm(f => ({...f, langue:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                  <option value="ar">العربية</option>
                  <option value="fr">Français</option>
                  <option value="en">English</option>
                </select>
              </div>
            </div>
            <div style={{ display:'flex', gap:'10px', marginTop:'16px' }}>
              <button onClick={() => setShowAddLivre(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={addLivre} disabled={saving || !form.titre} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Ajout...' : '✅ Ajouter au catalogue'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal émettre prêt */}
      {showAddPret && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowAddPret(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'440px', maxWidth:'90%' }} onClick={e => e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📤 Émettre un prêt</h3>
            {[
              ['ID Livre *', 'livre_id', 'Coller l\'UUID du livre'],
              ['ID Emprunteur *', 'emprunteur_id', 'UUID élève ou enseignant'],
            ].map(([label, key, placeholder]) => (
              <div key={key} style={{ marginBottom:'12px' }}>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                <input value={pretForm[key]} onChange={e => setPretForm(f => ({...f, [key]:e.target.value}))}
                  placeholder={placeholder}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            ))}
            <div style={{ marginBottom:'12px' }}>
              <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Type d'emprunteur</label>
              <select value={pretForm.emprunteur_type} onChange={e => setPretForm(f => ({...f, emprunteur_type:e.target.value}))}
                style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                <option value="eleve">👦 Élève</option>
                <option value="enseignant">👨‍🏫 Enseignant</option>
                <option value="personnel">👷 Personnel</option>
              </select>
            </div>
            <div style={{ background:'#1e3a5f22', border:'1px solid #2563eb33', borderRadius:'8px', padding:'10px', marginBottom:'14px', fontSize:'11px', color:'#93C5FD' }}>
              ℹ️ Durée par défaut : 14 jours · Max 3 livres/élève · Amende : 50 DA/jour de retard
            </div>
            <div style={{ display:'flex', gap:'10px' }}>
              <button onClick={() => setShowAddPret(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={emettrePret} disabled={saving || !pretForm.livre_id || !pretForm.emprunteur_id}
                style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Émission...' : '📤 Émettre le prêt'}
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
import BibliothequeePage from '@pages/BibliothequeePage';
// Dans les routes :
<Route path="bibliotheque" element={<BibliothequeePage />} />
```

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Dans la section "Gestion Centre", ajouter :
```jsx
{ label: 'Bibliothèque', path: '/bibliotheque', icon: '📚' },
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Controllers/BibliothequeControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Livre;
use App\Models\PretLivre;
use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class BibliothequeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeLivre(array $attrs = []): Livre
    {
        return Livre::create(array_merge([
            'tenant_id'      => Str::uuid(),
            'titre'          => 'Mathématiques 3AS',
            'auteur'         => 'Benali Mohamed',
            'categorie'      => 'manuel_scolaire',
            'nb_exemplaires' => 3,
            'nb_disponibles' => 3,
            'langue'         => 'ar',
            'actif'          => true,
        ], $attrs));
    }

    // ── Catalogue ─────────────────────────────────────────────────────

    public function test_dashboard_bibliotheque(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/bibliotheque/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'total_livres', 'livres_disponibles', 'prets_en_cours', 'prets_en_retard',
            ]]);
    }

    public function test_lister_catalogue(): void
    {
        $this->makeLivre();
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/bibliotheque/livres')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_ajouter_livre(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/livres', [
                'titre'          => 'Physique Chimie 2AS',
                'auteur'         => 'Khelil Ahmed',
                'categorie'      => 'manuel_scolaire',
                'nb_exemplaires' => 5,
                'langue'         => 'ar',
                'prix_remplacement' => 800,
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_ajouter_livre_sans_titre_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/livres', ['categorie' => 'autre'])
            ->assertStatus(422);
    }

    // ── Prêts ─────────────────────────────────────────────────────────

    public function test_emettre_pret(): void
    {
        $livre = $this->makeLivre(['nb_disponibles' => 2]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/prets', [
                'livre_id'       => $livre->id,
                'emprunteur_id'  => $eleve->id,
                'emprunteur_type'=> 'eleve',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        // Vérifier que le stock a décrémenté
        $this->assertEquals(1, $livre->fresh()->nb_disponibles);
    }

    public function test_pret_livre_non_disponible_echoue(): void
    {
        $livre = $this->makeLivre(['nb_disponibles' => 0]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/prets', [
                'livre_id'      => $livre->id,
                'emprunteur_id' => $eleve->id,
            ])
            ->assertStatus(422);
    }

    public function test_retour_livre(): void
    {
        $livre = $this->makeLivre();
        $eleve = Eleve::factory()->create();

        // Créer un prêt directement
        $pret = PretLivre::create([
            'tenant_id'          => $livre->tenant_id,
            'livre_id'           => $livre->id,
            'emprunteur_id'      => $eleve->id,
            'emprunteur_type'    => 'eleve',
            'gere_par'           => $this->admin->id,
            'date_pret'          => today(),
            'date_retour_prevue' => today()->addDays(14),
            'statut'             => 'en_cours',
        ]);
        $livre->decrement('nb_disponibles');

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/bibliotheque/prets/{$pret->id}/retour")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Vérifier que le stock est revenu
        $this->assertEquals($livre->nb_disponibles + 1, $livre->fresh()->nb_disponibles);
    }

    public function test_retour_avec_retard_genere_amende(): void
    {
        $livre = $this->makeLivre();
        $eleve = Eleve::factory()->create();

        $pret = PretLivre::create([
            'tenant_id'          => $livre->tenant_id,
            'livre_id'           => $livre->id,
            'emprunteur_id'      => $eleve->id,
            'emprunteur_type'    => 'eleve',
            'gere_par'           => $this->admin->id,
            'date_pret'          => today()->subDays(20),
            'date_retour_prevue' => today()->subDays(6), // 6 jours de retard
            'statut'             => 'en_retard',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/bibliotheque/prets/{$pret->id}/retour")
            ->assertStatus(200);

        $this->assertGreaterThan(0, $response->json('data.amende'));
    }

    public function test_renouveler_pret(): void
    {
        $livre = $this->makeLivre();
        $eleve = Eleve::factory()->create();

        $pret = PretLivre::create([
            'tenant_id'          => $livre->tenant_id,
            'livre_id'           => $livre->id,
            'emprunteur_id'      => $eleve->id,
            'emprunteur_type'    => 'eleve',
            'gere_par'           => $this->admin->id,
            'date_pret'          => today(),
            'date_retour_prevue' => today()->addDays(14),
            'statut'             => 'en_cours',
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/bibliotheque/prets/{$pret->id}/renouveler")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_lister_prets(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/bibliotheque/prets')
            ->assertStatus(200);
    }

    // ── Réservations ──────────────────────────────────────────────────

    public function test_reserver_livre_indisponible(): void
    {
        $livre = $this->makeLivre(['nb_disponibles' => 0]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/reservations', [
                'livre_id'      => $livre->id,
                'reservant_id'  => $eleve->id,
                'reservant_type'=> 'eleve',
            ])
            ->assertStatus(201);
    }

    public function test_reserver_livre_disponible_echoue(): void
    {
        $livre = $this->makeLivre(['nb_disponibles' => 2]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/reservations', [
                'livre_id'      => $livre->id,
                'reservant_id'  => $eleve->id,
            ])
            ->assertStatus(422); // Livre dispo → pas de réservation
    }

    // ── Config ────────────────────────────────────────────────────────

    public function test_config_par_defaut(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/bibliotheque/config')
            ->assertStatus(200)
            ->assertJsonPath('data.duree_pret_jours', 14)
            ->assertJsonPath('data.amende_par_jour', '50.00');
    }

    public function test_modifier_config(): void
    {
        $this->actingAs($this->admin, 'api')
            ->putJson('/api/v1/bibliotheque/config', [
                'duree_pret_jours' => 21,
                'amende_par_jour'  => 100,
                'nom_bibliotheque' => 'Bibliothèque Ibn Khaldoun',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.duree_pret_jours', 21);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/bibliotheque/livres')->assertStatus(401);
        $this->postJson('/api/v1/bibliotheque/prets', [])->assertStatus(401);
    }
}
```

---

## ÉTAPE 10 — Exécution finale

```bash
cd edugestdz/backend

# Migration
php artisan migrate

# Autoload
composer dump-autoload -o

# Tests
php artisan test --parallel
# → 0 régression + 14 nouveaux tests verts

# Commit
git add .
git commit -m "feat: Module Bibliothèque Scolaire — Catalogue + Prêts + Retours + Réservations + Amendes auto + Rappels SMS + Page React + 14 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_BIBLIOTHEQUE_SCOLAIRE.md — 10 étapes dans l'ordre.

RÈGLES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. Réutiliser SmsService, FirebaseService, ParentNotificationService existants.
   Si ParentNotificationService n'existe pas → remplacer par FirebaseService.notifyParentsEleve().
4. Ne pas modifier les contrôleurs existants.
5. Les 5 tables doivent être dans UNE SEULE migration.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
