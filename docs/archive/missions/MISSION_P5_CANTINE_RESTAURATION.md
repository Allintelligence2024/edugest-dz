# 🤖 MISSION DEEPSEEK — Priorité 5 : M10 Restauration / Cantine
## EduGest DZ · Branche : develop · 29 Juin 2026
## Tests actuels : 245+ ✅ (après merge PR #2) · Objectif : 262+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE (ne pas recréer)
- `app/Models/Eleve.php` — modèle élève avec `parents()`
- `app/Services/Sms/SmsService.php` — SMS opérationnel
- `app/Traits/BelongsToTenant.php` — isolation tenant
- `app/Models/BaseModel.php` — UUID auto
- `app/Http/Controllers/Api/BaseApiController.php` — helpers
- Dernière migration : `2026_06_29_600000` → utiliser `2026_06_29_700000`

### Ce qui MANQUE — M10 Cantine complet
```
Migration  : 2026_06_29_700000_create_cantine_restauration_tables
Models     : MenuCantine · InscriptionCantine · RepasJournalier · StockCuisine
Controller : CantineController.php
Factory    : MenuCantineFactory.php · InscriptionCantineFactory.php
Tests      : Feature/Api/CantineTest.php
Routes     : bloc cantine dans api.php
```

### Logique métier M10
- Un **menu** est défini par date (plat + accompagnement + dessert + prix unitaire)
- Un **élève** s'inscrit à la cantine (forfait mensuel ou journalier, avec régime alimentaire)
- Un **repas journalier** = présence effective d'un élève un jour donné (pointage)
- Le **stock cuisine** suit les ingrédients avec alertes de seuil bas
- La **facturation cantine** s'ajoute à la facture mensuelle de l'élève
- Le **menu est visible** dans l'app parent de la semaine

---

## ÉTAPE 1 — Migration

**Créer :** `edugestdz/backend/database/migrations/2026_06_29_700000_create_cantine_restauration_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Menus de la cantine ──
        Schema::create('menus_cantine', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->date('date_repas');
            $table->enum('type_repas', ['dejeuner', 'diner', 'petit_dejeuner'])->default('dejeuner');
            $table->string('plat_principal', 200);
            $table->string('accompagnement', 200)->nullable();
            $table->string('dessert', 150)->nullable();
            $table->string('boisson', 100)->nullable();
            $table->decimal('prix_unitaire', 8, 2)->default(0);
            $table->unsignedSmallInteger('nb_couverts_prevus')->default(0);
            $table->text('allergenes')->nullable();  // JSON liste allergènes
            $table->text('note')->nullable();
            $table->boolean('publie')->default(true); // visible app parent

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'date_repas', 'type_repas']);
        });

        // ── Inscriptions à la cantine ──
        Schema::create('inscriptions_cantine', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');

            $table->enum('type_abonnement', ['mensuel', 'journalier'])->default('mensuel');
            $table->enum('regime', ['normal', 'sans_porc', 'vegetarien', 'sans_gluten', 'autre'])
                  ->default('normal');
            $table->string('allergies', 300)->nullable(); // liste textuelle des allergies
            $table->boolean('actif')->default(true);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('tarif_mensuel', 8, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id', 'eleve_id', 'date_debut']);
        });

        // ── Repas journaliers (pointage effectif) ──
        Schema::create('repas_journaliers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('menu_id')->nullable();

            $table->date('date_repas');
            $table->enum('type_repas', ['dejeuner', 'diner', 'petit_dejeuner'])->default('dejeuner');
            $table->boolean('present')->default(false);  // a effectivement mangé
            $table->boolean('facture')->default(false);  // inclus dans la facture du mois
            $table->decimal('prix_applique', 8, 2)->default(0);
            $table->string('signale_par', 30)->default('admin'); // admin | badge | parent

            $table->timestamps();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menus_cantine')->onDelete('set null');
            $table->unique(['tenant_id', 'eleve_id', 'date_repas', 'type_repas']);
        });

        // ── Stock cuisine ──
        Schema::create('stock_cuisine', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('article', 150);
            $table->enum('categorie', [
                'legumes', 'viandes', 'poissons', 'produits_laitiers',
                'cereales', 'condiments', 'boissons', 'autres',
            ])->default('autres');
            $table->string('unite', 20)->default('kg');  // kg, litre, pièce, boite
            $table->decimal('quantite_stock', 10, 3)->default(0);
            $table->decimal('seuil_alerte', 10, 3)->default(0);  // alerte si en-dessous
            $table->decimal('prix_unitaire', 8, 2)->nullable();
            $table->string('fournisseur', 150)->nullable();
            $table->date('date_peremption')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Mouvements de stock cuisine ──
        Schema::create('mouvements_stock_cuisine', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            $table->enum('type', ['entree', 'sortie', 'ajustement'])->default('entree');
            $table->decimal('quantite', 10, 3);
            $table->string('motif', 200)->nullable();
            $table->uuid('saisie_par')->nullable();
            $table->date('date_mouvement');

            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('stock_cuisine')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock_cuisine');
        Schema::dropIfExists('stock_cuisine');
        Schema::dropIfExists('repas_journaliers');
        Schema::dropIfExists('inscriptions_cantine');
        Schema::dropIfExists('menus_cantine');
    }
};
```

---

## ÉTAPE 2 — Modèle MenuCantine

**Créer :** `edugestdz/backend/app/Models/MenuCantine.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuCantine extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'menus_cantine';

    protected $fillable = [
        'tenant_id', 'date_repas', 'type_repas',
        'plat_principal', 'accompagnement', 'dessert',
        'boisson', 'prix_unitaire', 'nb_couverts_prevus',
        'allergenes', 'note', 'publie',
    ];

    protected $casts = [
        'date_repas'   => 'date',
        'prix_unitaire'=> 'decimal:2',
        'publie'       => 'boolean',
    ];

    public function repasJournaliers(): HasMany
    {
        return $this->hasMany(RepasJournalier::class, 'menu_id');
    }

    public function getNbPresentsAttribute(): int
    {
        return $this->repasJournaliers()->where('present', true)->count();
    }

    public function scopePublies($query)
    {
        return $query->where('publie', true);
    }

    public function scopeSemaine($query, string $dateDebut, string $dateFin)
    {
        return $query->whereBetween('date_repas', [$dateDebut, $dateFin]);
    }
}
```

---

## ÉTAPE 3 — Modèle InscriptionCantine

**Créer :** `edugestdz/backend/app/Models/InscriptionCantine.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InscriptionCantine extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'inscriptions_cantine';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'type_abonnement', 'regime',
        'allergies', 'actif', 'date_debut', 'date_fin',
        'tarif_mensuel', 'note',
    ];

    protected $casts = [
        'date_debut'    => 'date',
        'date_fin'      => 'date',
        'actif'         => 'boolean',
        'tarif_mensuel' => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function repas(): HasMany
    {
        return $this->hasMany(RepasJournalier::class, 'eleve_id', 'eleve_id');
    }

    public function isActif(): bool
    {
        if (!$this->actif) return false;
        if ($this->date_fin && $this->date_fin->isPast()) return false;
        return true;
    }

    public function getRegimeLabelAttribute(): string
    {
        return match ($this->regime) {
            'sans_porc'    => 'Sans porc',
            'vegetarien'   => 'Végétarien',
            'sans_gluten'  => 'Sans gluten',
            'autre'        => 'Régime spécial',
            default        => 'Normal',
        };
    }
}
```

---

## ÉTAPE 4 — Modèle RepasJournalier

**Créer :** `edugestdz/backend/app/Models/RepasJournalier.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepasJournalier extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'repas_journaliers';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'menu_id', 'date_repas',
        'type_repas', 'present', 'facture', 'prix_applique', 'signale_par',
    ];

    protected $casts = [
        'date_repas'   => 'date',
        'present'      => 'boolean',
        'facture'      => 'boolean',
        'prix_applique'=> 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MenuCantine::class, 'menu_id');
    }
}
```

---

## ÉTAPE 5 — Modèle StockCuisine

**Créer :** `edugestdz/backend/app/Models/StockCuisine.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCuisine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'stock_cuisine';

    protected $fillable = [
        'tenant_id', 'article', 'categorie', 'unite',
        'quantite_stock', 'seuil_alerte', 'prix_unitaire',
        'fournisseur', 'date_peremption', 'note',
    ];

    protected $casts = [
        'quantite_stock' => 'decimal:3',
        'seuil_alerte'   => 'decimal:3',
        'prix_unitaire'  => 'decimal:2',
        'date_peremption'=> 'date',
    ];

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStockCuisine::class, 'article_id');
    }

    public function getEnAlertAttribute(): bool
    {
        return $this->quantite_stock <= $this->seuil_alerte;
    }

    public function getPerimeSoonAttribute(): bool
    {
        return $this->date_peremption && $this->date_peremption->diffInDays(now()) <= 7
            && !$this->date_peremption->isPast();
    }

    public function scopeEnAlerte($query)
    {
        return $query->whereColumn('quantite_stock', '<=', 'seuil_alerte');
    }
}
```

---

## ÉTAPE 6 — Modèle MouvementStockCuisine

**Créer :** `edugestdz/backend/app/Models/MouvementStockCuisine.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStockCuisine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mouvements_stock_cuisine';

    protected $fillable = [
        'tenant_id', 'article_id', 'type', 'quantite',
        'motif', 'saisie_par', 'date_mouvement',
    ];

    protected $casts = [
        'date_mouvement' => 'date',
        'quantite'       => 'decimal:3',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(StockCuisine::class, 'article_id');
    }
}
```

---

## ÉTAPE 7 — Controller CantineController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/CantineController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Eleve;
use App\Models\InscriptionCantine;
use App\Models\MenuCantine;
use App\Models\MouvementStockCuisine;
use App\Models\RepasJournalier;
use App\Models\StockCuisine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M10 — Restauration / Cantine
 *
 * Menus
 * GET    /api/v1/cantine/menus                 → liste menus (filtrables par semaine)
 * POST   /api/v1/cantine/menus                 → créer menu du jour
 * PUT    /api/v1/cantine/menus/{id}            → modifier menu
 * DELETE /api/v1/cantine/menus/{id}            → supprimer
 * GET    /api/v1/cantine/menus/semaine          → menus de la semaine (vue parent)
 *
 * Inscriptions
 * GET    /api/v1/cantine/inscriptions           → liste inscrits
 * POST   /api/v1/cantine/inscriptions           → inscrire élève
 * PUT    /api/v1/cantine/inscriptions/{id}      → modifier inscription
 * DELETE /api/v1/cantine/inscriptions/{id}      → désinscrire (soft)
 *
 * Pointage repas
 * POST   /api/v1/cantine/pointage              → pointer élèves du jour
 * GET    /api/v1/cantine/pointage/{date}        → pointage d'une date
 *
 * Stock cuisine
 * GET    /api/v1/cantine/stock                  → liste articles stock
 * POST   /api/v1/cantine/stock                  → ajouter article
 * POST   /api/v1/cantine/stock/{id}/mouvement   → entrée/sortie stock
 * GET    /api/v1/cantine/stock/alertes          → articles en alerte
 *
 * Dashboard
 * GET    /api/v1/cantine/dashboard             → synthèse du jour
 */
class CantineController extends BaseApiController
{
    // ═══════════════════════════════════════════
    // MENUS
    // ═══════════════════════════════════════════

    public function indexMenus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $debut = $validated['debut'] ?? today()->startOfWeek()->toDateString();
        $fin   = $validated['fin']   ?? today()->endOfWeek()->toDateString();

        $paginator = MenuCantine::whereBetween('date_repas', [$debut, $fin])
            ->orderBy('date_repas')
            ->paginate($validated['per_page'] ?? 30);

        return $this->paginatedResponse($paginator, "Menus du {$debut} au {$fin}", [
            'periode' => compact('debut', 'fin'),
        ]);
    }

    public function menuSemaine(Request $request): JsonResponse
    {
        $semaine = $request->filled('semaine')
            ? Carbon::parse($request->semaine)->startOfWeek()
            : now()->startOfWeek();

        $debut = $semaine->toDateString();
        $fin   = $semaine->copy()->endOfWeek()->toDateString();

        $menus = MenuCantine::publies()
            ->semaine($debut, $fin)
            ->orderBy('date_repas')
            ->get()
            ->groupBy(fn($m) => $m->date_repas->format('Y-m-d'))
            ->map(fn($menus, $date) => [
                'date'   => $date,
                'label'  => Carbon::parse($date)->translatedFormat('l d/m'),
                'repas'  => $menus->values(),
            ])
            ->values();

        return $this->success([
            'semaine_debut' => $debut,
            'semaine_fin'   => $fin,
            'jours'         => $menus,
        ], "Menu semaine du {$debut} au {$fin}");
    }

    public function storeMenu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_repas'        => 'required|date',
            'type_repas'        => 'nullable|in:dejeuner,diner,petit_dejeuner',
            'plat_principal'    => 'required|string|max:200',
            'accompagnement'    => 'nullable|string|max:200',
            'dessert'           => 'nullable|string|max:150',
            'boisson'           => 'nullable|string|max:100',
            'prix_unitaire'     => 'required|numeric|min:0',
            'nb_couverts_prevus'=> 'nullable|integer|min:0',
            'allergenes'        => 'nullable|string|max:300',
            'note'              => 'nullable|string|max:500',
        ]);

        $menu = MenuCantine::create($validated);

        return $this->created($menu, "Menu du {$menu->date_repas->format('d/m/Y')} créé");
    }

    public function updateMenu(Request $request, string $id): JsonResponse
    {
        $menu      = MenuCantine::findOrFail($id);
        $validated = $request->validate([
            'plat_principal'    => 'sometimes|string|max:200',
            'accompagnement'    => 'nullable|string|max:200',
            'dessert'           => 'nullable|string|max:150',
            'prix_unitaire'     => 'sometimes|numeric|min:0',
            'nb_couverts_prevus'=> 'nullable|integer|min:0',
            'publie'            => 'sometimes|boolean',
            'note'              => 'nullable|string|max:500',
        ]);

        $menu->update($validated);
        return $this->success($menu->fresh(), 'Menu mis à jour');
    }

    public function destroyMenu(string $id): JsonResponse
    {
        $menu = MenuCantine::findOrFail($id);
        $menu->delete();
        return $this->success(null, "Menu du {$menu->date_repas->format('d/m/Y')} supprimé");
    }

    // ═══════════════════════════════════════════
    // INSCRIPTIONS
    // ═══════════════════════════════════════════

    public function indexInscriptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regime'   => 'nullable|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'actif'    => 'nullable|boolean',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = InscriptionCantine::with('eleve:id,nom,prenom,photo_url,niveau_scolaire')
            ->when(isset($validated['actif']), fn($q) => $q->where('actif', $validated['actif']))
            ->when(!empty($validated['regime']), fn($q) => $q->where('regime', $validated['regime']));

        if (!empty($validated['search'])) {
            $query->whereHas('eleve', fn($q) => $q
                ->where('nom', 'ILIKE', "%{$validated['search']}%")
                ->orWhere('prenom', 'ILIKE', "%{$validated['search']}%")
            );
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_inscrits' => InscriptionCantine::where('actif', true)->count(),
            'par_regime'     => InscriptionCantine::where('actif', true)
                ->selectRaw('regime, COUNT(*) as total')
                ->groupBy('regime')->pluck('total', 'regime'),
        ];

        return $this->paginatedResponse($paginator, 'Inscriptions cantine', ['stats' => $stats]);
    }

    public function inscrireEleve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'type_abonnement'=> 'required|in:mensuel,journalier',
            'regime'         => 'required|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'      => 'nullable|string|max:300',
            'date_debut'     => 'required|date',
            'date_fin'       => 'nullable|date|after:date_debut',
            'tarif_mensuel'  => 'required|numeric|min:0',
            'note'           => 'nullable|string|max:300',
        ]);

        $eleve = Eleve::findOrFail($validated['eleve_id']);

        // Vérifier inscription active en double
        $dejaInscrit = InscriptionCantine::where('eleve_id', $validated['eleve_id'])
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return $this->error(
                "{$eleve->prenom} {$eleve->nom} est déjà inscrit(e) à la cantine",
                'DEJA_INSCRIT', 409
            );
        }

        $inscription = InscriptionCantine::create($validated);

        return $this->created([
            'inscription'  => $inscription,
            'eleve'        => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'regime_label' => $inscription->regime_label,
        ], "{$eleve->prenom} {$eleve->nom} inscrit(e) à la cantine ({$inscription->regime_label})");
    }

    public function updateInscription(Request $request, string $id): JsonResponse
    {
        $inscription = InscriptionCantine::findOrFail($id);
        $validated   = $request->validate([
            'regime'        => 'sometimes|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'     => 'nullable|string|max:300',
            'actif'         => 'sometimes|boolean',
            'tarif_mensuel' => 'sometimes|numeric|min:0',
            'date_fin'      => 'nullable|date',
            'note'          => 'nullable|string|max:300',
        ]);

        $inscription->update($validated);
        return $this->success($inscription->fresh('eleve'), 'Inscription mise à jour');
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $inscription = InscriptionCantine::with('eleve')->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);
        $nom = "{$inscription->eleve->prenom} {$inscription->eleve->nom}";
        return $this->success(null, "{$nom} désinscrit(e) de la cantine");
    }

    // ═══════════════════════════════════════════
    // POINTAGE REPAS
    // ═══════════════════════════════════════════

    public function pointer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'       => 'nullable|date',
            'type_repas' => 'required|in:dejeuner,diner,petit_dejeuner',
            'pointages'  => 'required|array|min:1',
            'pointages.*.eleve_id' => 'required|uuid|exists:eleves,id',
            'pointages.*.present'  => 'required|boolean',
        ]);

        $date    = $validated['date'] ?? today()->toDateString();
        $menu    = MenuCantine::where('date_repas', $date)
            ->where('type_repas', $validated['type_repas'])
            ->first();

        $enregistres = 0;
        foreach ($validated['pointages'] as $p) {
            RepasJournalier::updateOrCreate(
                [
                    'tenant_id'  => config('tenant.current_id'),
                    'eleve_id'   => $p['eleve_id'],
                    'date_repas' => $date,
                    'type_repas' => $validated['type_repas'],
                ],
                [
                    'menu_id'       => $menu?->id,
                    'present'       => $p['present'],
                    'prix_applique' => $p['present'] ? ($menu?->prix_unitaire ?? 0) : 0,
                    'signale_par'   => 'admin',
                ]
            );
            $enregistres++;
        }

        return $this->success([
            'date'        => $date,
            'type_repas'  => $validated['type_repas'],
            'enregistres' => $enregistres,
            'presents'    => collect($validated['pointages'])->where('present', true)->count(),
            'absents'     => collect($validated['pointages'])->where('present', false)->count(),
        ], "{$enregistres} repas pointé(s) pour le {$date}");
    }

    public function pointageDate(string $date): JsonResponse
    {
        $repas = RepasJournalier::with('eleve:id,nom,prenom', 'menu')
            ->where('date_repas', $date)
            ->get();

        $menu = MenuCantine::where('date_repas', $date)
            ->where('type_repas', 'dejeuner')
            ->first();

        return $this->success([
            'date'     => $date,
            'menu'     => $menu,
            'repas'    => $repas,
            'stats'    => [
                'total'   => $repas->count(),
                'presents'=> $repas->where('present', true)->count(),
                'absents' => $repas->where('present', false)->count(),
                'ca_jour' => $repas->where('present', true)->sum('prix_applique'),
            ],
        ]);
    }

    // ═══════════════════════════════════════════
    // STOCK CUISINE
    // ═══════════════════════════════════════════

    public function indexStock(Request $request): JsonResponse
    {
        $query = StockCuisine::query();

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        $articles = $query->orderBy('article')->get()->map(fn($a) => array_merge(
            $a->toArray(),
            ['en_alerte' => $a->en_alert, 'perime_soon' => $a->perime_soon]
        ));

        return $this->success([
            'articles'    => $articles,
            'nb_alertes'  => $articles->where('en_alerte', true)->count(),
            'nb_articles' => $articles->count(),
        ], 'Stock cuisine récupéré');
    }

    public function storeStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article'         => 'required|string|max:150',
            'categorie'       => 'required|in:legumes,viandes,poissons,produits_laitiers,cereales,condiments,boissons,autres',
            'unite'           => 'required|string|max:20',
            'quantite_stock'  => 'required|numeric|min:0',
            'seuil_alerte'    => 'required|numeric|min:0',
            'prix_unitaire'   => 'nullable|numeric|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'date_peremption' => 'nullable|date|after:today',
        ]);

        $article = StockCuisine::create($validated);
        return $this->created($article, "Article '{$article->article}' ajouté au stock");
    }

    public function mouvementStock(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|in:entree,sortie,ajustement',
            'quantite'       => 'required|numeric|min:0.001',
            'motif'          => 'nullable|string|max:200',
            'date_mouvement' => 'nullable|date',
        ]);

        $article = StockCuisine::findOrFail($id);

        // Calculer nouvelle quantité
        $nouvelleQte = match ($validated['type']) {
            'entree'     => $article->quantite_stock + $validated['quantite'],
            'sortie'     => $article->quantite_stock - $validated['quantite'],
            'ajustement' => $validated['quantite'],
        };

        if ($nouvelleQte < 0) {
            return $this->error(
                "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
        }

        MouvementStockCuisine::create([
            'tenant_id'      => config('tenant.current_id'),
            'article_id'     => $article->id,
            'type'           => $validated['type'],
            'quantite'       => $validated['quantite'],
            'motif'          => $validated['motif'] ?? null,
            'saisie_par'     => auth()->id(),
            'date_mouvement' => $validated['date_mouvement'] ?? today(),
        ]);

        $article->update(['quantite_stock' => $nouvelleQte]);

        return $this->success([
            'article'       => $article->fresh(),
            'mouvement'     => $validated['type'],
            'quantite'      => $validated['quantite'],
            'nouveau_stock' => $nouvelleQte,
            'en_alerte'     => $nouvelleQte <= $article->seuil_alerte,
        ], "Stock {$article->article} mis à jour : {$nouvelleQte} {$article->unite}");
    }

    public function alertesStock(): JsonResponse
    {
        $articles = StockCuisine::enAlerte()
            ->orderBy('quantite_stock')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'deficit'    => max(0, $a->seuil_alerte - $a->quantite_stock),
                'perime_soon'=> $a->perime_soon,
            ]));

        return $this->success([
            'alertes'     => $articles,
            'nb_alertes'  => $articles->count(),
        ], "{$articles->count()} article(s) en alerte de stock");
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today = today();

        // Menu du jour
        $menuDuJour = MenuCantine::where('date_repas', $today)
            ->where('type_repas', 'dejeuner')
            ->first();

        // Inscrits actifs
        $inscritsActifs = InscriptionCantine::where('actif', true)->count();

        // Présents aujourd'hui
        $presentsAujourdhui = RepasJournalier::where('date_repas', $today)
            ->where('present', true)
            ->count();

        // Répartition régimes
        $parRegime = InscriptionCantine::where('actif', true)
            ->selectRaw('regime, COUNT(*) as total')
            ->groupBy('regime')
            ->pluck('total', 'regime');

        // Alertes stock
        $nbAlertesStock = StockCuisine::enAlerte()->count();

        // CA cantine ce mois
        $caMois = RepasJournalier::where('present', true)
            ->whereMonth('date_repas', $today->month)
            ->whereYear('date_repas', $today->year)
            ->sum('prix_applique');

        // Menu de la semaine
        $debut = $today->copy()->startOfWeek()->toDateString();
        $fin   = $today->copy()->endOfWeek()->toDateString();
        $menusSemaine = MenuCantine::publies()
            ->semaine($debut, $fin)
            ->orderBy('date_repas')
            ->get(['id', 'date_repas', 'plat_principal', 'prix_unitaire']);

        return $this->success([
            'date'                => $today->format('d/m/Y'),
            'menu_du_jour'        => $menuDuJour,
            'inscrits_actifs'     => $inscritsActifs,
            'presents_aujourdhui' => $presentsAujourdhui,
            'taux_presence'       => $inscritsActifs > 0
                ? round(($presentsAujourdhui / $inscritsActifs) * 100, 1) : 0,
            'par_regime'          => $parRegime,
            'alertes_stock'       => $nbAlertesStock,
            'ca_mois'             => (float) $caMois,
            'menus_semaine'       => $menusSemaine,
        ], "Tableau de bord cantine — {$today->format('d/m/Y')}");
    }
}
```

---

## ÉTAPE 8 — Factories

**Créer :** `edugestdz/backend/database/factories/MenuCantineFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\MenuCantine;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCantineFactory extends Factory
{
    protected $model = MenuCantine::class;

    public function definition(): array
    {
        $plats = ['Poulet rôti', 'Couscous', 'Tajine', 'Lentilles', 'Sardines grillées', 'Bœuf aux légumes'];
        return [
            'date_repas'         => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'type_repas'         => 'dejeuner',
            'plat_principal'     => $this->faker->randomElement($plats),
            'accompagnement'     => $this->faker->randomElement(['Riz', 'Semoule', 'Frites', 'Légumes vapeur']),
            'dessert'            => $this->faker->randomElement(['Fruit', 'Yaourt', 'Cake', null]),
            'boisson'            => 'Eau',
            'prix_unitaire'      => $this->faker->numberBetween(150, 400),
            'nb_couverts_prevus' => $this->faker->numberBetween(20, 80),
            'publie'             => true,
        ];
    }
}
```

**Créer :** `edugestdz/backend/database/factories/InscriptionCantineFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\InscriptionCantine;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscriptionCantineFactory extends Factory
{
    protected $model = InscriptionCantine::class;

    public function definition(): array
    {
        return [
            'type_abonnement'=> 'mensuel',
            'regime'         => $this->faker->randomElement(['normal', 'sans_porc', 'vegetarien']),
            'actif'          => true,
            'date_debut'     => now()->startOfMonth()->toDateString(),
            'tarif_mensuel'  => $this->faker->numberBetween(2000, 5000),
        ];
    }

    public function sansPorc(): static
    {
        return $this->state(['regime' => 'sans_porc']);
    }

    public function vegetarien(): static
    {
        return $this->state(['regime' => 'vegetarien']);
    }
}
```

---

## ÉTAPE 9 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter dans le groupe `middleware(['auth:api', 'resolve.tenant', 'check.subscription'])` :

```php
// ── Cantine / Restauration (M10) ──
Route::prefix('cantine')->group(function () {
    Route::get('dashboard',                      [\App\Http\Controllers\Api\V1\CantineController::class, 'dashboard']);

    // Menus
    Route::get('menus',                          [\App\Http\Controllers\Api\V1\CantineController::class, 'indexMenus']);
    Route::get('menus/semaine',                  [\App\Http\Controllers\Api\V1\CantineController::class, 'menuSemaine']);
    Route::post('menus',                         [\App\Http\Controllers\Api\V1\CantineController::class, 'storeMenu']);
    Route::put('menus/{id}',                     [\App\Http\Controllers\Api\V1\CantineController::class, 'updateMenu']);
    Route::delete('menus/{id}',                  [\App\Http\Controllers\Api\V1\CantineController::class, 'destroyMenu']);

    // Inscriptions
    Route::get('inscriptions',                   [\App\Http\Controllers\Api\V1\CantineController::class, 'indexInscriptions']);
    Route::post('inscriptions',                  [\App\Http\Controllers\Api\V1\CantineController::class, 'inscrireEleve']);
    Route::put('inscriptions/{id}',              [\App\Http\Controllers\Api\V1\CantineController::class, 'updateInscription']);
    Route::delete('inscriptions/{id}',           [\App\Http\Controllers\Api\V1\CantineController::class, 'desinscrireEleve']);

    // Pointage repas
    Route::post('pointage',                      [\App\Http\Controllers\Api\V1\CantineController::class, 'pointer']);
    Route::get('pointage/{date}',                [\App\Http\Controllers\Api\V1\CantineController::class, 'pointageDate']);

    // Stock cuisine
    Route::get('stock',                          [\App\Http\Controllers\Api\V1\CantineController::class, 'indexStock']);
    Route::post('stock',                         [\App\Http\Controllers\Api\V1\CantineController::class, 'storeStock']);
    Route::post('stock/{id}/mouvement',          [\App\Http\Controllers\Api\V1\CantineController::class, 'mouvementStock']);
    Route::get('stock/alertes',                  [\App\Http\Controllers\Api\V1\CantineController::class, 'alertesStock']);
});
```

---

## ÉTAPE 10 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/CantineTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\InscriptionCantine;
use App\Models\MenuCantine;
use App\Models\Role;
use App\Models\StockCuisine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CantineTest extends TestCase
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

    // ─── MENUS ───────────────────────────────────────

    public function test_creer_menu(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/menus', [
                'date_repas'     => today()->addDay()->toDateString(),
                'plat_principal' => 'Couscous au poulet',
                'accompagnement' => 'Légumes',
                'dessert'        => 'Fruit',
                'prix_unitaire'  => 250,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.plat_principal', 'Couscous au poulet');

        $this->assertDatabaseHas('menus_cantine', [
            'plat_principal' => 'Couscous au poulet',
            'tenant_id'      => $this->tenant->id,
        ]);
    }

    public function test_menu_semaine_structure(): void
    {
        MenuCantine::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/menus/semaine')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['semaine_debut', 'semaine_fin', 'jours']]);
    }

    public function test_isolation_tenant_menus(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreMenu   = MenuCantine::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/cantine/menus/{$autreMenu->id}", ['plat_principal' => 'Hack'])
            ->assertStatus(404);
    }

    // ─── INSCRIPTIONS ────────────────────────────────

    public function test_inscrire_eleve_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id'       => $eleve->id,
                'type_abonnement'=> 'mensuel',
                'regime'         => 'sans_porc',
                'date_debut'     => today()->toDateString(),
                'tarif_mensuel'  => 3000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.inscription.regime', 'sans_porc');

        $this->assertDatabaseHas('inscriptions_cantine', [
            'eleve_id'  => $eleve->id,
            'regime'    => 'sans_porc',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_double_inscription_bloquee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        InscriptionCantine::create([
            'tenant_id'      => $this->tenant->id,
            'eleve_id'       => $eleve->id,
            'type_abonnement'=> 'mensuel',
            'regime'         => 'normal',
            'actif'          => true,
            'date_debut'     => today(),
            'tarif_mensuel'  => 3000,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id'       => $eleve->id,
                'type_abonnement'=> 'mensuel',
                'regime'         => 'vegetarien',
                'date_debut'     => today()->toDateString(),
                'tarif_mensuel'  => 3000,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_INSCRIT');
    }

    public function test_liste_inscrits_filtree_par_tenant(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        InscriptionCantine::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'type_abonnement' => 'mensuel', 'regime' => 'normal',
            'actif' => true, 'date_debut' => today(), 'tarif_mensuel' => 3000,
        ]);

        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        InscriptionCantine::create([
            'tenant_id' => $autreTenant->id, 'eleve_id' => $autreEleve->id,
            'type_abonnement' => 'mensuel', 'regime' => 'normal',
            'actif' => true, 'date_debut' => today(), 'tarif_mensuel' => 3000,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/inscriptions')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    // ─── POINTAGE REPAS ──────────────────────────────

    public function test_pointer_repas(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/pointage', [
                'date'       => today()->toDateString(),
                'type_repas' => 'dejeuner',
                'pointages'  => [
                    ['eleve_id' => $eleve->id, 'present' => true],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.presents', 1);

        $this->assertDatabaseHas('repas_journaliers', [
            'eleve_id' => $eleve->id,
            'present'  => true,
        ]);
    }

    public function test_pointage_date_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/pointage/' . today()->toDateString())
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['date', 'menu', 'repas', 'stats']]);
    }

    // ─── STOCK ───────────────────────────────────────

    public function test_ajouter_article_stock(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/stock', [
                'article'        => 'Poulet frais',
                'categorie'      => 'viandes',
                'unite'          => 'kg',
                'quantite_stock' => 50,
                'seuil_alerte'   => 10,
                'prix_unitaire'  => 650,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.article', 'Poulet frais');
    }

    public function test_sortie_stock_diminue_quantite(): void
    {
        $article = StockCuisine::create([
            'tenant_id'      => $this->tenant->id,
            'article'        => 'Tomates',
            'categorie'      => 'legumes',
            'unite'          => 'kg',
            'quantite_stock' => 20,
            'seuil_alerte'   => 5,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/cantine/stock/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 8,
                'motif'    => 'Repas du jour',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.nouveau_stock', '12.000');
    }

    public function test_stock_insuffisant_bloque(): void
    {
        $article = StockCuisine::create([
            'tenant_id'      => $this->tenant->id,
            'article'        => 'Farine',
            'categorie'      => 'cereales',
            'unite'          => 'kg',
            'quantite_stock' => 3,
            'seuil_alerte'   => 5,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/cantine/stock/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 10, // plus que le stock
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STOCK_INSUFFISANT');
    }

    public function test_alertes_stock(): void
    {
        // Article EN alerte
        StockCuisine::create([
            'tenant_id' => $this->tenant->id, 'article' => 'Huile',
            'categorie' => 'condiments', 'unite' => 'litre',
            'quantite_stock' => 2, 'seuil_alerte' => 5,
        ]);

        // Article OK
        StockCuisine::create([
            'tenant_id' => $this->tenant->id, 'article' => 'Sel',
            'categorie' => 'condiments', 'unite' => 'kg',
            'quantite_stock' => 20, 'seuil_alerte' => 2,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/stock/alertes')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_alertes', 1);
    }

    // ─── DASHBOARD ───────────────────────────────────

    public function test_dashboard_cantine_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'date', 'menu_du_jour', 'inscrits_actifs',
                    'presents_aujourdhui', 'taux_presence',
                    'par_regime', 'alertes_stock', 'ca_mois', 'menus_semaine',
                ],
            ]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre merge PR #2 (contenant P1+P2+P3+P4) dans main
git checkout develop
git pull origin main

# 1. Créer la migration
create: edugestdz/backend/database/migrations/2026_06_29_700000_create_cantine_restauration_tables.php

# 2. Créer les modèles (dans cet ordre)
create: edugestdz/backend/app/Models/MenuCantine.php
create: edugestdz/backend/app/Models/InscriptionCantine.php
create: edugestdz/backend/app/Models/RepasJournalier.php
create: edugestdz/backend/app/Models/StockCuisine.php
create: edugestdz/backend/app/Models/MouvementStockCuisine.php

# 3. Créer le controller
create: edugestdz/backend/app/Http/Controllers/Api/V1/CantineController.php

# 4. Créer les factories
create: edugestdz/backend/database/factories/MenuCantineFactory.php
create: edugestdz/backend/database/factories/InscriptionCantineFactory.php

# 5. Ajouter les routes dans api.php
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('cantine') dans le groupe auth:api

# 6. Créer les tests
create: edugestdz/backend/tests/Feature/Api/CantineTest.php

# 7. Lancer la migration
php artisan migrate

# 8. Lancer les tests
php artisan test --parallel
# → Attendu : tests précédents + 14 nouveaux = 259+ tests verts

# 9. Si tout est vert
git add .
git commit -m "feat: M10 Cantine — Menus + Inscriptions + Pointage + Stock cuisine + 14 tests"
git push origin develop

# 10. Ouvrir PR develop → main sur GitHub
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends merge PR #2 (P1+P2+P3+P4) dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P5_CANTINE_RESTAURATION.md — 10 étapes dans l'ordre.
php artisan test --parallel → 259+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
