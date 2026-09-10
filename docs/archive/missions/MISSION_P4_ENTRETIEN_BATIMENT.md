# 🤖 MISSION DEEPSEEK — Priorité 4 : M14 Entretien Bâtiment
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Tests actuels : 291+ ✅ · Objectif : 303+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE (ne pas recréer)
- `app/Models/Depense.php` → catégorie `'maintenance_reparation'` déjà présente dans M13
- `app/Services/FacturationService.php` / `BudgetController.php` → M13 complet
- `app/Traits/BelongsToTenant.php` + `BaseModel.php` + `BaseApiController.php`
- Dernière migration : `2026_06_30_400000` → utiliser `2026_07_01_100000`

### Lien clé avec M13
Chaque intervention d'entretien qui a un coût **crée automatiquement une dépense** dans M13
(catégorie `maintenance_reparation`). C'est l'intégration principale de ce module.

### Ce qui MANQUE — M14 complet
```
Migration  : create_entretien_batiment_tables
Models     : LocalBatiment · InterventionEntretien · PrestatireEntretien
Controller : EntretienController
Factory    : InterventionEntretienFactory
Tests      : EntretienBatimentTest.php
Routes     : bloc entretien dans api.php
```

---

## ÉTAPE 1 — Migration

**Créer :** `edugestdz/backend/database/migrations/2026_07_01_100000_create_entretien_batiment_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Locaux / zones du bâtiment ──
        Schema::create('locaux_batiment', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 100);            // "Salle 101", "Cour", "WC Nord"
            $table->enum('type', [
                'salle_cours', 'bureau', 'couloir', 'cour',
                'sanitaires', 'cantine', 'gymnase', 'entree',
                'parking', 'laboratoire', 'bibliotheque', 'autre',
            ])->default('salle_cours');
            $table->string('etage', 20)->nullable();   // "RDC", "1er étage"
            $table->float('superficie_m2')->nullable();
            $table->enum('etat_general', ['bon', 'moyen', 'mauvais', 'critique'])
                  ->default('bon');
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Prestataires d'entretien ──
        Schema::create('prestataires_entretien', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 150);
            $table->enum('specialite', [
                'plomberie', 'electricite', 'peinture', 'climatisation',
                'menuiserie', 'maconnerie', 'nettoyage', 'informatique',
                'jardinage', 'securite', 'general', 'autre',
            ])->default('general');
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 200)->nullable();
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Interventions / tickets d'entretien ──
        Schema::create('interventions_entretien', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('local_id')->nullable();           // local concerné
            $table->uuid('prestataire_id')->nullable();     // prestataire assigné

            $table->string('titre', 200);
            $table->text('description')->nullable();

            $table->enum('type', [
                'panne',              // panne soudaine à réparer
                'degradation',        // dégradation constatée
                'entretien_preventif',// entretien programmé
                'renovation',         // travaux de rénovation
                'nettoyage',          // nettoyage spécial
                'inspection',         // visite de contrôle
            ])->default('panne');

            $table->enum('priorite', ['urgente', 'haute', 'normale', 'basse'])
                  ->default('normale');

            $table->enum('statut', [
                'signale',     // nouveau ticket
                'en_cours',    // intervention en cours
                'en_attente',  // en attente de pièce/prestataire
                'resolu',      // résolu
                'annule',      // annulé
            ])->default('signale');

            $table->date('date_signalement');
            $table->date('date_debut_intervention')->nullable();
            $table->date('date_resolution')->nullable();
            $table->date('date_entretien_suivant')->nullable(); // pour préventif

            $table->decimal('cout_estime', 10, 2)->nullable();
            $table->decimal('cout_reel', 10, 2)->nullable();
            $table->uuid('depense_id')->nullable();  // lien vers M13 Depense

            $table->string('photos_avant', 1000)->nullable(); // JSON array URLs
            $table->string('photos_apres', 1000)->nullable(); // JSON array URLs

            $table->uuid('signale_par')->nullable();
            $table->uuid('assigne_a')->nullable();
            $table->text('rapport_intervention')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('local_id')->references('id')->on('locaux_batiment')->onDelete('set null');
            $table->foreign('prestataire_id')->references('id')->on('prestataires_entretien')->onDelete('set null');
        });

        // ── Entretiens préventifs planifiés ──
        Schema::create('entretiens_preventifs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('local_id')->nullable();
            $table->uuid('prestataire_id')->nullable();

            $table->string('nom', 150);               // "Nettoyage climatisation"
            $table->text('description')->nullable();
            $table->enum('frequence', [
                'hebdomadaire', 'mensuel', 'trimestriel',
                'semestriel', 'annuel', 'biennal',
            ])->default('annuel');

            $table->date('prochaine_echeance');
            $table->date('derniere_realisation')->nullable();
            $table->decimal('cout_estime', 10, 2)->nullable();
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entretiens_preventifs');
        Schema::dropIfExists('interventions_entretien');
        Schema::dropIfExists('prestataires_entretien');
        Schema::dropIfExists('locaux_batiment');
    }
};
```

---

## ÉTAPE 2 — Modèle LocalBatiment

**Créer :** `edugestdz/backend/app/Models/LocalBatiment.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalBatiment extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'locaux_batiment';

    protected $fillable = [
        'tenant_id', 'nom', 'type', 'etage',
        'superficie_m2', 'etat_general', 'actif', 'note',
    ];

    protected $casts = [
        'actif'        => 'boolean',
        'superficie_m2'=> 'float',
    ];

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'salle_cours'  => 'Salle de cours',
            'bureau'       => 'Bureau',
            'couloir'      => 'Couloir',
            'cour'         => 'Cour',
            'sanitaires'   => 'Sanitaires',
            'cantine'      => 'Cantine',
            'gymnase'      => 'Gymnase',
            'entree'       => 'Entrée',
            'parking'      => 'Parking',
            'laboratoire'  => 'Laboratoire',
            'bibliotheque' => 'Bibliothèque',
            default        => 'Autre',
        };
    }

    public function getEtatLabelAttribute(): string
    {
        return match ($this->etat_general) {
            'bon'      => 'Bon état',
            'moyen'    => 'État moyen',
            'mauvais'  => 'Mauvais état',
            'critique' => 'État critique',
            default    => ucfirst($this->etat_general),
        };
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'local_id');
    }

    public function interventionsOuvertes(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'local_id')
            ->whereIn('statut', ['signale', 'en_cours', 'en_attente']);
    }

    public function entretiensPlanifies(): HasMany
    {
        return $this->hasMany(EntretienPreventif::class, 'local_id');
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }
}
```

---

## ÉTAPE 3 — Modèle PrestatireEntretien

**Créer :** `edugestdz/backend/app/Models/PrestatireEntretien.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestatireEntretien extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'prestataires_entretien';

    protected $fillable = [
        'tenant_id', 'nom', 'specialite', 'telephone',
        'email', 'adresse', 'actif', 'note',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function getSpecialiteLabelAttribute(): string
    {
        return match ($this->specialite) {
            'plomberie'    => 'Plomberie',
            'electricite'  => 'Électricité',
            'peinture'     => 'Peinture',
            'climatisation'=> 'Climatisation',
            'menuiserie'   => 'Menuiserie',
            'maconnerie'   => 'Maçonnerie',
            'nettoyage'    => 'Nettoyage',
            'informatique' => 'Informatique',
            'jardinage'    => 'Jardinage',
            'securite'     => 'Sécurité',
            'general'      => 'Général',
            default        => 'Autre',
        };
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'prestataire_id');
    }
}
```

---

## ÉTAPE 4 — Modèle InterventionEntretien

**Créer :** `edugestdz/backend/app/Models/InterventionEntretien.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterventionEntretien extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'interventions_entretien';

    protected $fillable = [
        'tenant_id', 'local_id', 'prestataire_id', 'titre', 'description',
        'type', 'priorite', 'statut', 'date_signalement',
        'date_debut_intervention', 'date_resolution', 'date_entretien_suivant',
        'cout_estime', 'cout_reel', 'depense_id',
        'photos_avant', 'photos_apres',
        'signale_par', 'assigne_a', 'rapport_intervention',
    ];

    protected $casts = [
        'date_signalement'         => 'date',
        'date_debut_intervention'  => 'date',
        'date_resolution'          => 'date',
        'date_entretien_suivant'   => 'date',
        'cout_estime'              => 'decimal:2',
        'cout_reel'                => 'decimal:2',
        'photos_avant'             => 'array',
        'photos_apres'             => 'array',
    ];

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalBatiment::class, 'local_id');
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(PrestatireEntretien::class, 'prestataire_id');
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class, 'depense_id');
    }

    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signale_par');
    }

    public function getPrioriteLabelAttribute(): string
    {
        return match ($this->priorite) {
            'urgente' => '🔴 Urgente',
            'haute'   => '🟠 Haute',
            'normale' => '🟡 Normale',
            'basse'   => '🟢 Basse',
            default   => $this->priorite,
        ];
    }

    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'signale'    => 'Signalé',
            'en_cours'   => 'En cours',
            'en_attente' => 'En attente',
            'resolu'     => 'Résolu',
            'annule'     => 'Annulé',
            default      => ucfirst($this->statut),
        ];
    }

    public function getDureeJoursAttribute(): ?int
    {
        if (!$this->date_signalement) return null;
        $fin = $this->date_resolution ?? today();
        return $this->date_signalement->diffInDays($fin);
    }

    public function scopeOuverts($query)
    {
        return $query->whereIn('statut', ['signale', 'en_cours', 'en_attente']);
    }

    public function scopePriorite($query, string $priorite)
    {
        return $query->where('priorite', $priorite);
    }
}
```

---

## ÉTAPE 5 — Modèle EntretienPreventif

**Créer :** `edugestdz/backend/app/Models/EntretienPreventif.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntretienPreventif extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'entretiens_preventifs';

    protected $fillable = [
        'tenant_id', 'local_id', 'prestataire_id', 'nom', 'description',
        'frequence', 'prochaine_echeance', 'derniere_realisation',
        'cout_estime', 'actif',
    ];

    protected $casts = [
        'prochaine_echeance'    => 'date',
        'derniere_realisation'  => 'date',
        'cout_estime'           => 'decimal:2',
        'actif'                 => 'boolean',
    ];

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalBatiment::class, 'local_id');
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(PrestatireEntretien::class, 'prestataire_id');
    }

    public function getEnRetardAttribute(): bool
    {
        return $this->actif
            && $this->prochaine_echeance
            && $this->prochaine_echeance->isPast();
    }

    public function getJoursAvantEcheanceAttribute(): int
    {
        if (!$this->prochaine_echeance) return 999;
        return (int) today()->diffInDays($this->prochaine_echeance, false);
    }
}
```

---

## ÉTAPE 6 — Controller EntretienController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/EntretienController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * M14 — Entretien Bâtiment
 *
 * Locaux
 * GET    /api/v1/entretien/locaux                        → liste locaux
 * POST   /api/v1/entretien/locaux                        → créer local
 * PUT    /api/v1/entretien/locaux/{id}                   → modifier
 * DELETE /api/v1/entretien/locaux/{id}                   → supprimer
 *
 * Prestataires
 * GET    /api/v1/entretien/prestataires                  → liste prestataires
 * POST   /api/v1/entretien/prestataires                  → créer
 * PUT    /api/v1/entretien/prestataires/{id}             → modifier
 *
 * Interventions (tickets)
 * GET    /api/v1/entretien/interventions                 → liste (filtrables)
 * POST   /api/v1/entretien/interventions                 → signaler nouvelle intervention
 * GET    /api/v1/entretien/interventions/{id}            → détail
 * PUT    /api/v1/entretien/interventions/{id}/statut     → changer statut
 * PUT    /api/v1/entretien/interventions/{id}/resoudre   → résoudre avec coût réel
 *
 * Préventif
 * GET    /api/v1/entretien/preventif                     → liste entretiens planifiés
 * POST   /api/v1/entretien/preventif                     → planifier entretien récurrent
 * PUT    /api/v1/entretien/preventif/{id}/realiser       → marquer comme réalisé
 *
 * Dashboard
 * GET    /api/v1/entretien/dashboard                     → synthèse
 */
class EntretienController extends BaseApiController
{
    // ═══════════════════════════════════════════
    // LOCAUX
    // ═══════════════════════════════════════════

    public function indexLocaux(Request $request): JsonResponse
    {
        $locaux = LocalBatiment::actifs()
            ->withCount(['interventionsOuvertes as tickets_ouverts'])
            ->orderBy('nom')
            ->get()
            ->map(fn($l) => array_merge($l->toArray(), [
                'type_label'  => $l->type_label,
                'etat_label'  => $l->etat_label,
            ]));

        return $this->success([
            'locaux' => $locaux,
            'stats'  => [
                'total'    => $locaux->count(),
                'critique' => $locaux->where('etat_general', 'critique')->count(),
                'mauvais'  => $locaux->where('etat_general', 'mauvais')->count(),
            ],
        ], 'Locaux récupérés');
    }

    public function storeLocal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:100',
            'type'         => 'required|in:salle_cours,bureau,couloir,cour,sanitaires,cantine,gymnase,entree,parking,laboratoire,bibliotheque,autre',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'etat_general' => 'nullable|in:bon,moyen,mauvais,critique',
            'note'         => 'nullable|string|max:500',
        ]);

        $local = LocalBatiment::create($validated);
        return $this->created(
            array_merge($local->toArray(), ['type_label' => $local->type_label]),
            "Local '{$local->nom}' créé"
        );
    }

    public function updateLocal(Request $request, string $id): JsonResponse
    {
        $local     = LocalBatiment::findOrFail($id);
        $validated = $request->validate([
            'nom'          => 'sometimes|string|max:100',
            'etat_general' => 'sometimes|in:bon,moyen,mauvais,critique',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'note'         => 'nullable|string|max:500',
        ]);

        $local->update($validated);
        return $this->success(
            array_merge($local->fresh()->toArray(), ['etat_label' => $local->fresh()->etat_label]),
            'Local mis à jour'
        );
    }

    public function destroyLocal(string $id): JsonResponse
    {
        $local = LocalBatiment::findOrFail($id);
        if ($local->interventionsOuvertes()->exists()) {
            return $this->error(
                'Des interventions sont en cours sur ce local',
                'HAS_INTERVENTIONS', 422
            );
        }
        $nom = $local->nom;
        $local->delete();
        return $this->success(null, "Local '{$nom}' supprimé");
    }

    // ═══════════════════════════════════════════
    // PRESTATAIRES
    // ═══════════════════════════════════════════

    public function indexPrestataires(): JsonResponse
    {
        $prestataires = PrestatireEntretien::where('actif', true)
            ->withCount('interventions')
            ->orderBy('nom')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), [
                'specialite_label' => $p->specialite_label,
            ]));

        return $this->success($prestataires, 'Prestataires récupérés');
    }

    public function storePrestataire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:150',
            'specialite'=> 'required|in:plomberie,electricite,peinture,climatisation,menuiserie,maconnerie,nettoyage,informatique,jardinage,securite,general,autre',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'adresse'   => 'nullable|string|max:200',
            'note'      => 'nullable|string|max:500',
        ]);

        $prestataire = PrestatireEntretien::create($validated);
        return $this->created($prestataire, "Prestataire '{$prestataire->nom}' ajouté");
    }

    public function updatePrestataire(Request $request, string $id): JsonResponse
    {
        $prestataire = PrestatireEntretien::findOrFail($id);
        $validated   = $request->validate([
            'nom'       => 'sometimes|string|max:150',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'actif'     => 'sometimes|boolean',
            'note'      => 'nullable|string|max:500',
        ]);
        $prestataire->update($validated);
        return $this->success($prestataire->fresh(), 'Prestataire mis à jour');
    }

    // ═══════════════════════════════════════════
    // INTERVENTIONS (TICKETS)
    // ═══════════════════════════════════════════

    public function indexInterventions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut'   => 'nullable|in:signale,en_cours,en_attente,resolu,annule',
            'priorite' => 'nullable|in:urgente,haute,normale,basse',
            'type'     => 'nullable|string',
            'local_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = InterventionEntretien::with([
            'local:id,nom,type',
            'prestataire:id,nom,specialite',
        ])
            ->when($validated['statut']   ?? null, fn($q, $s) => $q->where('statut', $s))
            ->when($validated['priorite'] ?? null, fn($q, $p) => $q->where('priorite', $p))
            ->when($validated['type']     ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($validated['local_id'] ?? null, fn($q, $l) => $q->where('local_id', $l))
            ->orderByRaw("CASE priorite
                WHEN 'urgente' THEN 1
                WHEN 'haute'   THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse'   THEN 4
                ELSE 5 END")
            ->orderByDesc('date_signalement')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_ouverts' => InterventionEntretien::ouverts()->count(),
            'urgentes'      => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'hautes'        => InterventionEntretien::ouverts()->priorite('haute')->count(),
            'resolues_mois' => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', now()->month)->count(),
        ];

        return $this->paginatedResponse($paginator, 'Interventions récupérées', ['stats' => $stats]);
    }

    public function showIntervention(string $id): JsonResponse
    {
        $intervention = InterventionEntretien::with([
            'local:id,nom,type,etage',
            'prestataire:id,nom,specialite,telephone',
            'signalePar:id,nom,prenom',
            'depense',
        ])->findOrFail($id);

        return $this->success([
            'intervention'   => $intervention,
            'priorite_label' => $intervention->priorite_label,
            'statut_label'   => $intervention->statut_label,
            'duree_jours'    => $intervention->duree_jours,
        ]);
    }

    public function signalerIntervention(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:panne,degradation,entretien_preventif,renovation,nettoyage,inspection',
            'priorite'        => 'required|in:urgente,haute,normale,basse',
            'local_id'        => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'  => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_signalement'=> 'nullable|date',
            'cout_estime'     => 'nullable|numeric|min:0',
        ]);

        $validated['date_signalement'] = $validated['date_signalement'] ?? today()->toDateString();
        $validated['signale_par']      = auth()->id();
        $validated['statut']           = 'signale';

        $intervention = InterventionEntretien::create($validated);

        return $this->created([
            'intervention'   => $intervention->load(['local', 'prestataire']),
            'priorite_label' => $intervention->priorite_label,
        ], "Intervention signalée : {$intervention->titre}");
    }

    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut'                  => 'required|in:en_cours,en_attente,annule',
            'prestataire_id'          => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_debut_intervention' => 'nullable|date',
        ]);

        $intervention = InterventionEntretien::findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return $this->error('Cette intervention est déjà résolue', 'DEJA_RESOLU', 409);
        }

        $data = ['statut' => $validated['statut']];

        if ($validated['statut'] === 'en_cours') {
            $data['date_debut_intervention'] = $validated['date_debut_intervention'] ?? today()->toDateString();
            if (isset($validated['prestataire_id'])) {
                $data['prestataire_id'] = $validated['prestataire_id'];
            }
        }

        $intervention->update($data);
        return $this->success(
            $intervention->fresh(['local', 'prestataire']),
            "Statut mis à jour : {$intervention->statut_label}"
        );
    }

    public function resoudreIntervention(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'cout_reel'            => 'required|numeric|min:0',
            'rapport_intervention' => 'nullable|string|max:2000',
            'date_resolution'      => 'nullable|date',
            'date_entretien_suivant'=> 'nullable|date|after:today',
            'etat_local_apres'     => 'nullable|in:bon,moyen,mauvais,critique',
        ]);

        $intervention = InterventionEntretien::with('local')->findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return $this->error('Déjà résolu', 'DEJA_RESOLU', 409);
        }

        DB::transaction(function () use ($intervention, $validated) {
            // ── Résoudre l'intervention ──
            $intervention->update([
                'statut'                => 'resolu',
                'cout_reel'             => $validated['cout_reel'],
                'rapport_intervention'  => $validated['rapport_intervention'] ?? null,
                'date_resolution'       => $validated['date_resolution'] ?? today()->toDateString(),
                'date_entretien_suivant'=> $validated['date_entretien_suivant'] ?? null,
            ]);

            // ── Mettre à jour l'état du local ──
            if (isset($validated['etat_local_apres']) && $intervention->local) {
                $intervention->local->update(['etat_general' => $validated['etat_local_apres']]);
            }

            // ── Créer automatiquement une dépense M13 si coût > 0 ──
            if ($validated['cout_reel'] > 0) {
                $depense = Depense::create([
                    'tenant_id'    => config('tenant.current_id'),
                    'categorie'    => 'maintenance_reparation',
                    'libelle'      => "Entretien : {$intervention->titre}",
                    'montant'      => $validated['cout_reel'],
                    'date_depense' => today()->toDateString(),
                    'mois'         => now()->month,
                    'annee'        => now()->year,
                    'fournisseur'  => $intervention->prestataire?->nom,
                    'mode_paiement'=> 'cash',
                    'statut'       => 'validee',
                    'saisie_par'   => auth()->id(),
                    'note'         => "Lié à l'intervention #{$intervention->id}",
                ]);

                $intervention->update(['depense_id' => $depense->id]);
            }
        });

        return $this->success([
            'intervention' => $intervention->fresh(['local', 'prestataire', 'depense']),
            'depense_creee'=> $validated['cout_reel'] > 0,
        ], "Intervention résolue — Coût : " . number_format($validated['cout_reel'], 2) . " DA");
    }

    // ═══════════════════════════════════════════
    // ENTRETIENS PRÉVENTIFS
    // ═══════════════════════════════════════════

    public function indexPreventif(): JsonResponse
    {
        $entretiens = EntretienPreventif::where('actif', true)
            ->with(['local:id,nom', 'prestataire:id,nom'])
            ->orderBy('prochaine_echeance')
            ->get()
            ->map(fn($e) => array_merge($e->toArray(), [
                'en_retard'              => $e->en_retard,
                'jours_avant_echeance'   => $e->jours_avant_echeance,
            ]));

        $alertes = $entretiens->filter(fn($e) => $e['jours_avant_echeance'] <= 30)->count();

        return $this->success([
            'entretiens' => $entretiens,
            'alertes_30j'=> $alertes,
        ], 'Entretiens préventifs récupérés');
    }

    public function planifierPreventif(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'                 => 'required|string|max:150',
            'description'         => 'nullable|string|max:500',
            'local_id'            => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'      => 'nullable|uuid|exists:prestataires_entretien,id',
            'frequence'           => 'required|in:hebdomadaire,mensuel,trimestriel,semestriel,annuel,biennal',
            'prochaine_echeance'  => 'required|date',
            'cout_estime'         => 'nullable|numeric|min:0',
        ]);

        $entretien = EntretienPreventif::create($validated);
        return $this->created($entretien->load(['local', 'prestataire']), "Entretien planifié : {$entretien->nom}");
    }

    public function realiserPreventif(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'cout_reel'   => 'nullable|numeric|min:0',
            'observations'=> 'nullable|string|max:500',
        ]);

        $entretien = EntretienPreventif::findOrFail($id);

        // Calculer la prochaine échéance selon la fréquence
        $prochaine = match ($entretien->frequence) {
            'hebdomadaire' => now()->addWeek(),
            'mensuel'      => now()->addMonth(),
            'trimestriel'  => now()->addMonths(3),
            'semestriel'   => now()->addMonths(6),
            'annuel'       => now()->addYear(),
            'biennal'      => now()->addYears(2),
            default        => now()->addYear(),
        };

        $entretien->update([
            'derniere_realisation'  => today(),
            'prochaine_echeance'    => $prochaine->toDateString(),
        ]);

        // Créer une dépense M13 si coût > 0
        if (($validated['cout_reel'] ?? 0) > 0) {
            Depense::create([
                'tenant_id'    => config('tenant.current_id'),
                'categorie'    => 'maintenance_reparation',
                'libelle'      => "Entretien préventif : {$entretien->nom}",
                'montant'      => $validated['cout_reel'],
                'date_depense' => today()->toDateString(),
                'mois'         => now()->month,
                'annee'        => now()->year,
                'fournisseur'  => $entretien->prestataire?->nom,
                'mode_paiement'=> 'cash',
                'statut'       => 'validee',
                'saisie_par'   => auth()->id(),
            ]);
        }

        return $this->success([
            'entretien'        => $entretien->fresh(),
            'prochaine_echeance'=> $prochaine->format('d/m/Y'),
        ], "Entretien réalisé · Prochain : {$prochaine->format('d/m/Y')}");
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today = today();

        $stats = [
            'tickets_ouverts'   => InterventionEntretien::ouverts()->count(),
            'tickets_urgents'   => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'resolus_ce_mois'   => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->count(),
            'cout_mois'         => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->sum('cout_reel'),
            'locaux_critique'   => LocalBatiment::actifs()->where('etat_general', 'critique')->count(),
            'preventifs_retard' => EntretienPreventif::where('actif', true)
                ->where('prochaine_echeance', '<', $today)->count(),
            'preventifs_30j'    => EntretienPreventif::where('actif', true)
                ->whereBetween('prochaine_echeance', [$today, $today->copy()->addDays(30)])->count(),
        ];

        $derniersTickets = InterventionEntretien::ouverts()
            ->with(['local:id,nom', 'prestataire:id,nom'])
            ->orderByRaw("CASE priorite WHEN 'urgente' THEN 1 WHEN 'haute' THEN 2 ELSE 3 END")
            ->limit(5)->get();

        return $this->success([
            'stats'          => $stats,
            'derniers_tickets'=> $derniersTickets,
        ], 'Tableau de bord entretien bâtiment');
    }
}
```

---

## ÉTAPE 7 — Factory

**Créer :** `edugestdz/backend/database/factories/InterventionEntretienFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\InterventionEntretien;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterventionEntretienFactory extends Factory
{
    protected $model = InterventionEntretien::class;

    public function definition(): array
    {
        return [
            'titre'            => $this->faker->sentence(5),
            'description'      => $this->faker->paragraph(),
            'type'             => $this->faker->randomElement(['panne', 'degradation', 'entretien_preventif']),
            'priorite'         => $this->faker->randomElement(['urgente', 'haute', 'normale', 'basse']),
            'statut'           => 'signale',
            'date_signalement' => today()->toDateString(),
        ];
    }

    public function urgente(): static
    {
        return $this->state(['priorite' => 'urgente']);
    }

    public function resolue(): static
    {
        return $this->state([
            'statut'          => 'resolu',
            'date_resolution' => today()->toDateString(),
            'cout_reel'       => $this->faker->numberBetween(5000, 100000),
        ]);
    }
}
```

---

## ÉTAPE 8 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

```php
// ── Entretien Bâtiment (M14) ──
Route::prefix('entretien')->group(function () {
    Route::get('dashboard',                              [\App\Http\Controllers\Api\V1\EntretienController::class, 'dashboard']);

    // Locaux
    Route::get('locaux',                                 [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexLocaux']);
    Route::post('locaux',                                [\App\Http\Controllers\Api\V1\EntretienController::class, 'storeLocal']);
    Route::put('locaux/{id}',                            [\App\Http\Controllers\Api\V1\EntretienController::class, 'updateLocal']);
    Route::delete('locaux/{id}',                         [\App\Http\Controllers\Api\V1\EntretienController::class, 'destroyLocal']);

    // Prestataires
    Route::get('prestataires',                           [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexPrestataires']);
    Route::post('prestataires',                          [\App\Http\Controllers\Api\V1\EntretienController::class, 'storePrestataire']);
    Route::put('prestataires/{id}',                      [\App\Http\Controllers\Api\V1\EntretienController::class, 'updatePrestataire']);

    // Interventions
    Route::get('interventions',                          [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexInterventions']);
    Route::post('interventions',                         [\App\Http\Controllers\Api\V1\EntretienController::class, 'signalerIntervention']);
    Route::get('interventions/{id}',                     [\App\Http\Controllers\Api\V1\EntretienController::class, 'showIntervention']);
    Route::put('interventions/{id}/statut',              [\App\Http\Controllers\Api\V1\EntretienController::class, 'changerStatut']);
    Route::put('interventions/{id}/resoudre',            [\App\Http\Controllers\Api\V1\EntretienController::class, 'resoudreIntervention']);

    // Préventif
    Route::get('preventif',                              [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexPreventif']);
    Route::post('preventif',                             [\App\Http\Controllers\Api\V1\EntretienController::class, 'planifierPreventif']);
    Route::put('preventif/{id}/realiser',                [\App\Http\Controllers\Api\V1\EntretienController::class, 'realiserPreventif']);
});
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/EntretienBatimentTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntretienBatimentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    // ─── LOCAUX ──────────────────────────────────────

    public function test_creer_local(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/locaux', [
                'nom'          => 'Salle 101',
                'type'         => 'salle_cours',
                'etage'        => '1er étage',
                'superficie_m2'=> 45.5,
                'etat_general' => 'bon',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Salle 101')
            ->assertJsonPath('data.type_label', 'Salle de cours');

        $this->assertDatabaseHas('locaux_batiment', [
            'nom'       => 'Salle 101',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_locaux_par_tenant(): void
    {
        LocalBatiment::create(['tenant_id' => $this->tenant->id, 'nom' => 'Salle A', 'type' => 'salle_cours']);
        LocalBatiment::create(['tenant_id' => $this->tenant->id, 'nom' => 'Bureau', 'type' => 'bureau']);

        $autreTenant = Tenant::factory()->create();
        LocalBatiment::create(['tenant_id' => $autreTenant->id, 'nom' => 'Salle B', 'type' => 'salle_cours']);

        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/locaux')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 2);
    }

    // ─── PRESTATAIRES ────────────────────────────────

    public function test_creer_prestataire(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/prestataires', [
                'nom'        => 'Plomberie Alger',
                'specialite' => 'plomberie',
                'telephone'  => '0550123456',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Plomberie Alger');
    }

    // ─── INTERVENTIONS ───────────────────────────────

    public function test_signaler_intervention(): void
    {
        $local = LocalBatiment::create([
            'tenant_id' => $this->tenant->id, 'nom' => 'WC Nord', 'type' => 'sanitaires',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/interventions', [
                'titre'    => 'Fuite d\'eau robinet',
                'type'     => 'panne',
                'priorite' => 'haute',
                'local_id' => $local->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.intervention.statut', 'signale')
            ->assertJsonPath('data.priorite_label', '🟠 Haute');

        $this->assertDatabaseHas('interventions_entretien', [
            'titre'     => 'Fuite d\'eau robinet',
            'tenant_id' => $this->tenant->id,
            'statut'    => 'signale',
        ]);
    }

    public function test_changer_statut_intervention(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'signale',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_resoudre_cree_depense_m13(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'en_cours',
            'titre'     => 'Réparation plomberie',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/resoudre", [
                'cout_reel'            => 25000,
                'rapport_intervention' => 'Robinet remplacé.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.depense_creee', true);

        // Vérifier que la dépense M13 a été créée automatiquement
        $this->assertDatabaseHas('depenses', [
            'categorie' => 'maintenance_reparation',
            'montant'   => 25000,
            'tenant_id' => $this->tenant->id,
        ]);

        // Vérifier le lien depense_id dans l'intervention
        $this->assertNotNull(
            InterventionEntretien::find($intervention->id)->depense_id
        );
    }

    public function test_resoudre_sans_cout_ne_cree_pas_depense(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'en_cours',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/resoudre", [
                'cout_reel' => 0,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.depense_creee', false);

        $this->assertDatabaseCount('depenses', 0);
    }

    public function test_isolation_tenant_intervention(): void
    {
        $autreTenant    = Tenant::factory()->create();
        $autreIntervention = InterventionEntretien::factory()->create([
            'tenant_id' => $autreTenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/entretien/interventions/{$autreIntervention->id}")
            ->assertStatus(404);
    }

    // ─── PRÉVENTIF ───────────────────────────────────

    public function test_planifier_entretien_preventif(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/preventif', [
                'nom'                => 'Nettoyage climatisation',
                'frequence'          => 'semestriel',
                'prochaine_echeance' => now()->addMonths(3)->toDateString(),
                'cout_estime'        => 8000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.frequence', 'semestriel');
    }

    public function test_realiser_preventif_met_a_jour_echeance(): void
    {
        $entretien = EntretienPreventif::create([
            'tenant_id'          => $this->tenant->id,
            'nom'                => 'Contrôle extincteurs',
            'frequence'          => 'annuel',
            'prochaine_echeance' => today()->toDateString(),
            'actif'              => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/entretien/preventif/{$entretien->id}/realiser", [
                'cout_reel' => 5000,
            ])
            ->assertStatus(200);

        // La prochaine échéance doit être dans ~1 an
        $this->assertStringContainsString(
            (string) now()->addYear()->year,
            $response->json('data.prochaine_echeance')
        );
    }

    // ─── DASHBOARD ───────────────────────────────────

    public function test_dashboard_entretien(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['stats' => [
                    'tickets_ouverts', 'tickets_urgents',
                    'resolus_ce_mois', 'cout_mois',
                    'locaux_critique', 'preventifs_retard',
                ]],
            ]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre merge PR #6 (M11 Stock) dans main, puis synchroniser
git checkout develop
git pull origin main

# 1. Migration
create: edugestdz/backend/database/migrations/2026_07_01_100000_create_entretien_batiment_tables.php

# 2. Modèles (dans l'ordre)
create: edugestdz/backend/app/Models/LocalBatiment.php
create: edugestdz/backend/app/Models/PrestatireEntretien.php
create: edugestdz/backend/app/Models/InterventionEntretien.php
create: edugestdz/backend/app/Models/EntretienPreventif.php

# 3. Controller
create: edugestdz/backend/app/Http/Controllers/Api/V1/EntretienController.php

# 4. Factory
create: edugestdz/backend/database/factories/InterventionEntretienFactory.php

# 5. Routes
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('entretien')

# 6. Tests
create: edugestdz/backend/tests/Feature/Api/EntretienBatimentTest.php

# 7. Migration
php artisan migrate

# 8. Tests
php artisan test --parallel
# → Attendu : 291+ précédents + 10 nouveaux = 301+ tests verts

# 9. Si tout est vert
git add .
git commit -m "feat: M14 Entretien Bâtiment — Locaux + Interventions + Préventif + Dépenses M13 auto + 10 tests"
git push origin develop

# 10. Ouvrir PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends merge PR #6 (M11 Stock) dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P4_ENTRETIEN_BATIMENT.md — 10 étapes dans l'ordre.

Point clé : quand une intervention est résolue avec un coût > 0,
une dépense M13 (catégorie maintenance_reparation) est créée automatiquement.
C'est l'intégration principale avec le module budget.

php artisan test --parallel → 301+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
