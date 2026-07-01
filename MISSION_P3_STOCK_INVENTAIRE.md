# 🤖 MISSION DEEPSEEK — Priorité 3 : M11 Stock & Inventaire Mobilier
## EduGest DZ · Branche : develop · 30 Juin 2026
## Tests actuels : 282+ ✅ · Objectif : 298+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE déjà (ne pas recréer)
- `app/Models/StockCuisine.php` → stock cuisine cantine (M10) — modèle de référence pour M11
- `app/Models/MouvementStockCuisine.php` → mouvements stock cuisine — même logique
- `app/Traits/BelongsToTenant.php` → isolation tenant
- `app/Models/BaseModel.php` → UUID auto
- `app/Http/Controllers/Api/BaseApiController.php` → helpers réponse
- Dernière migration : `2026_06_30_300000` → utiliser `2026_06_30_400000`

### Différence M11 vs StockCuisine (M10)
- M10 = ingrédients alimentaires (kg, litre) → M11 = mobilier + fournitures (pièces, unités)
- M11 ajoute : QR code étiquetage, localisation par salle, état (bon/usé/hors_service)
- M11 ajoute : prêt de matériel (qui a emprunté quoi, retour attendu)
- M11 ajoute : rapport inventaire annuel (obligation légale Algérie)
- M11 ajoute : bons de commande fournisseurs générés en PDF

### Ce qui MANQUE — M11 complet
```
Migration  : create_stock_inventaire_tables
Models     : ArticleStock · MouvementStock · PretMateriel
Controller : StockInventaireController
Factory    : ArticleStockFactory
Template   : pdf/bon_commande.blade.php · pdf/rapport_inventaire.blade.php
Tests      : StockInventaireTest.php
Routes     : bloc stock-inventaire dans api.php
```

---

## ÉTAPE 1 — Migration

**Créer :** `edugestdz/backend/database/migrations/2026_06_30_400000_create_stock_inventaire_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Articles du stock / inventaire ──
        Schema::create('articles_stock', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 150);
            $table->string('reference', 50)->nullable();     // code interne ou ref fournisseur
            $table->string('qr_code', 100)->nullable()->unique(); // QR étiquetage physique

            $table->enum('categorie', [
                'mobilier',           // chaises, tables, bureaux, armoires
                'equipement_pedagogique', // tableaux, vidéoprojecteurs, ordinateurs
                'fourniture_bureau',  // papier, stylos, classeurs
                'fourniture_pedagogique', // craies, marqueurs, cahiers
                'equipement_sportif', // matériel sport
                'materiel_entretien', // balais, produits nettoyage
                'equipement_informatique', // imprimantes, scanners
                'autre',
            ]);

            $table->string('unite', 20)->default('pièce'); // pièce, unité, rame, boîte
            $table->uuid('salle_id')->nullable();           // localisation (FK → salles)
            $table->string('localisation', 100)->nullable(); // description libre si pas de salle

            // Stock
            $table->integer('quantite_stock')->default(0);
            $table->integer('quantite_minimum')->default(0);  // seuil alerte réapprovisionnement

            // État (pour le mobilier immobilisé)
            $table->enum('etat', ['bon', 'use', 'hors_service', 'en_reparation'])
                  ->default('bon');

            // Valeur comptable
            $table->decimal('valeur_unitaire', 10, 2)->nullable();
            $table->date('date_acquisition')->nullable();
            $table->string('fournisseur', 150)->nullable();
            $table->string('numero_serie', 100)->nullable();

            // Flags
            $table->boolean('est_immobilise')->default(false); // bien immobilisé (inventaire annuel)
            $table->boolean('actif')->default(true);

            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Mouvements de stock (entrée / sortie / ajustement / transfert) ──
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            $table->enum('type', ['entree', 'sortie', 'ajustement', 'transfert', 'perte'])
                  ->default('entree');
            $table->integer('quantite');
            $table->integer('quantite_avant')->default(0);  // stock avant mouvement
            $table->integer('quantite_apres')->default(0);  // stock après mouvement
            $table->string('motif', 200)->nullable();
            $table->string('reference_doc', 100)->nullable(); // N° bon de commande, N° facture...
            $table->uuid('saisie_par')->nullable();
            $table->date('date_mouvement');

            $table->timestamps();

            $table->foreign('article_id')
                ->references('id')->on('articles_stock')
                ->onDelete('cascade');
        });

        // ── Prêts de matériel ──
        Schema::create('prets_materiel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            // Emprunteur (enseignant ou personnel)
            $table->uuid('emprunteur_id')->nullable();
            $table->enum('type_emprunteur', ['enseignant', 'personnel', 'externe'])
                  ->default('enseignant');
            $table->string('nom_emprunteur', 150)->nullable(); // si externe

            $table->integer('quantite')->default(1);
            $table->date('date_pret');
            $table->date('date_retour_prevue');
            $table->date('date_retour_effective')->nullable();
            $table->enum('statut', ['en_cours', 'rendu', 'en_retard', 'perdu'])
                  ->default('en_cours');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('article_id')
                ->references('id')->on('articles_stock')
                ->onDelete('cascade');
        });

        // ── Bons de commande fournisseurs ──
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('numero', 30)->unique();          // BC-2026-001
            $table->string('fournisseur', 150);
            $table->string('fournisseur_contact', 150)->nullable();
            $table->date('date_commande');
            $table->date('date_livraison_prevue')->nullable();
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->enum('statut', ['brouillon', 'envoye', 'recu', 'partiel', 'annule'])
                  ->default('brouillon');
            $table->text('note')->nullable();
            $table->string('fichier_url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Lignes de bon de commande ──
        Schema::create('lignes_bon_commande', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('bon_commande_id');
            $table->uuid('article_id')->nullable();

            $table->string('designation', 200);
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();

            $table->foreign('bon_commande_id')
                ->references('id')->on('bons_commande')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bon_commande');
        Schema::dropIfExists('bons_commande');
        Schema::dropIfExists('prets_materiel');
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('articles_stock');
    }
};
```

---

## ÉTAPE 2 — Modèle ArticleStock

**Créer :** `edugestdz/backend/app/Models/ArticleStock.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ArticleStock extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'articles_stock';

    protected $fillable = [
        'tenant_id', 'nom', 'reference', 'qr_code', 'categorie',
        'unite', 'salle_id', 'localisation', 'quantite_stock',
        'quantite_minimum', 'etat', 'valeur_unitaire', 'date_acquisition',
        'fournisseur', 'numero_serie', 'est_immobilise', 'actif', 'note',
    ];

    protected $casts = [
        'date_acquisition' => 'date',
        'valeur_unitaire'  => 'decimal:2',
        'est_immobilise'   => 'boolean',
        'actif'            => 'boolean',
    ];

    // ── Auto-génération QR code unique ──
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (!$model->qr_code) {
                $model->qr_code = 'ART-' . strtoupper(Str::random(8));
            }
            if (!$model->reference) {
                $prefix = match ($model->categorie) {
                    'mobilier'                => 'MOB',
                    'equipement_pedagogique'  => 'PED',
                    'fourniture_bureau'       => 'FBU',
                    'fourniture_pedagogique'  => 'FPE',
                    'equipement_informatique' => 'INF',
                    default                   => 'ART',
                };
                $model->reference = $prefix . '-' . now()->year . '-' . str_pad(
                    static::withoutGlobalScope('tenant')->count() + 1, 4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    // ── Accesseurs ──
    public function getEnAlerteAttribute(): bool
    {
        return $this->quantite_stock <= $this->quantite_minimum;
    }

    public function getEtatLabelAttribute(): string
    {
        return match ($this->etat) {
            'bon'           => 'Bon état',
            'use'           => 'Usé',
            'hors_service'  => 'Hors service',
            'en_reparation' => 'En réparation',
            default         => ucfirst($this->etat),
        };
    }

    public function getCategorieLabelAttribute(): string
    {
        return match ($this->categorie) {
            'mobilier'                => 'Mobilier',
            'equipement_pedagogique'  => 'Équipement pédagogique',
            'fourniture_bureau'       => 'Fournitures bureau',
            'fourniture_pedagogique'  => 'Fournitures pédagogiques',
            'equipement_sportif'      => 'Équipement sportif',
            'materiel_entretien'      => 'Matériel entretien',
            'equipement_informatique' => 'Équipement informatique',
            default                   => 'Autre',
        };
    }

    public function getValeurTotaleAttribute(): float
    {
        return (float) ($this->valeur_unitaire ?? 0) * $this->quantite_stock;
    }

    // ── Relations ──
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'article_id');
    }

    public function prets(): HasMany
    {
        return $this->hasMany(PretMateriel::class, 'article_id');
    }

    public function pretsEnCours(): HasMany
    {
        return $this->hasMany(PretMateriel::class, 'article_id')
            ->where('statut', 'en_cours');
    }

    // ── Scopes ──
    public function scopeEnAlerte($query)
    {
        return $query->whereColumn('quantite_stock', '<=', 'quantite_minimum');
    }

    public function scopeImmobilises($query)
    {
        return $query->where('est_immobilise', true);
    }

    public function scopeCategorie($query, string $cat)
    {
        return $query->where('categorie', $cat);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'ILIKE', "%{$search}%")
              ->orWhere('reference', 'ILIKE', "%{$search}%")
              ->orWhere('qr_code', 'ILIKE', "%{$search}%");
        });
    }
}
```

---

## ÉTAPE 3 — Modèle MouvementStock

**Créer :** `edugestdz/backend/app/Models/MouvementStock.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'tenant_id', 'article_id', 'type', 'quantite',
        'quantite_avant', 'quantite_apres', 'motif',
        'reference_doc', 'saisie_par', 'date_mouvement',
    ];

    protected $casts = [
        'date_mouvement' => 'date',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }

    public function saisie(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }
}
```

---

## ÉTAPE 4 — Modèle PretMateriel

**Créer :** `edugestdz/backend/app/Models/PretMateriel.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PretMateriel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'prets_materiel';

    protected $fillable = [
        'tenant_id', 'article_id', 'emprunteur_id', 'type_emprunteur',
        'nom_emprunteur', 'quantite', 'date_pret',
        'date_retour_prevue', 'date_retour_effective', 'statut', 'note',
    ];

    protected $casts = [
        'date_pret'              => 'date',
        'date_retour_prevue'     => 'date',
        'date_retour_effective'  => 'date',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }

    public function getEnRetardAttribute(): bool
    {
        return $this->statut === 'en_cours'
            && $this->date_retour_prevue
            && $this->date_retour_prevue->isPast();
    }
}
```

---

## ÉTAPE 5 — Modèle BonCommande

**Créer :** `edugestdz/backend/app/Models/BonCommande.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonCommande extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'bons_commande';

    protected $fillable = [
        'tenant_id', 'numero', 'fournisseur', 'fournisseur_contact',
        'date_commande', 'date_livraison_prevue', 'montant_total',
        'statut', 'note', 'fichier_url',
    ];

    protected $casts = [
        'date_commande'         => 'date',
        'date_livraison_prevue' => 'date',
        'montant_total'         => 'decimal:2',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class, 'bon_commande_id');
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $last  = static::withoutGlobalScope('tenant')
            ->where('numero', 'LIKE', "BC-{$annee}-%")
            ->orderByDesc('numero')->value('numero');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;
        return sprintf('BC-%d-%03d', $annee, $seq);
    }
}
```

---

## ÉTAPE 6 — Modèle LigneBonCommande

**Créer :** `edugestdz/backend/app/Models/LigneBonCommande.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends BaseModel
{
    protected $table = 'lignes_bon_commande';

    protected $fillable = [
        'bon_commande_id', 'article_id', 'designation',
        'quantite', 'prix_unitaire', 'total',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }
}
```

---

## ÉTAPE 7 — Controller StockInventaireController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/StockInventaireController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ArticleStock;
use App\Models\BonCommande;
use App\Models\LigneBonCommande;
use App\Models\MouvementStock;
use App\Models\PretMateriel;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * M11 — Stock & Inventaire Mobilier
 *
 * Articles
 * GET    /api/v1/stock/articles                    → liste articles
 * POST   /api/v1/stock/articles                    → créer article
 * GET    /api/v1/stock/articles/{id}               → détail + historique mouvements
 * PUT    /api/v1/stock/articles/{id}               → modifier
 * DELETE /api/v1/stock/articles/{id}               → supprimer (soft)
 * GET    /api/v1/stock/articles/qr/{qr_code}       → trouver par QR
 *
 * Mouvements
 * POST   /api/v1/stock/articles/{id}/mouvement     → entrée/sortie/ajustement
 * GET    /api/v1/stock/articles/{id}/historique    → historique mouvements
 *
 * Alertes
 * GET    /api/v1/stock/alertes                     → articles sous seuil minimum
 *
 * Prêts
 * GET    /api/v1/stock/prets                       → liste prêts en cours
 * POST   /api/v1/stock/prets                       → créer un prêt
 * PUT    /api/v1/stock/prets/{id}/retour           → enregistrer retour
 *
 * Bons de commande
 * GET    /api/v1/stock/bons-commande               → liste bons
 * POST   /api/v1/stock/bons-commande               → créer bon
 * PUT    /api/v1/stock/bons-commande/{id}/statut   → changer statut
 * GET    /api/v1/stock/bons-commande/{id}/pdf      → télécharger PDF
 *
 * Rapports
 * GET    /api/v1/stock/rapport-inventaire          → rapport annuel PDF
 * GET    /api/v1/stock/dashboard                   → synthèse
 */
class StockInventaireController extends BaseApiController
{
    // ═══════════════════════════════════════════
    // ARTICLES
    // ═══════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'       => 'nullable|string|max:100',
            'categorie'    => 'nullable|string',
            'etat'         => 'nullable|in:bon,use,hors_service,en_reparation',
            'en_alerte'    => 'nullable|boolean',
            'immobilise'   => 'nullable|boolean',
            'per_page'     => 'nullable|integer|min:5|max:100',
        ]);

        $query = ArticleStock::where('actif', true);

        if (!empty($validated['search'])) {
            $query->search($validated['search']);
        }
        if (!empty($validated['categorie'])) {
            $query->categorie($validated['categorie']);
        }
        if (!empty($validated['etat'])) {
            $query->where('etat', $validated['etat']);
        }
        if (isset($validated['en_alerte']) && $validated['en_alerte']) {
            $query->enAlerte();
        }
        if (isset($validated['immobilise'])) {
            $query->where('est_immobilise', $validated['immobilise']);
        }

        $paginator = $query->orderBy('categorie')->orderBy('nom')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_articles'    => ArticleStock::where('actif', true)->count(),
            'articles_en_alerte'=> ArticleStock::where('actif', true)->enAlerte()->count(),
            'valeur_totale_stock'=> ArticleStock::where('actif', true)
                ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
                ->value('total') ?? 0,
            'par_categorie'     => ArticleStock::where('actif', true)
                ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as total_qte')
                ->groupBy('categorie')
                ->get(),
        ];

        return $this->paginatedResponse($paginator, 'Articles récupérés', ['stats' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'             => 'required|string|max:150',
            'categorie'       => 'required|in:mobilier,equipement_pedagogique,fourniture_bureau,fourniture_pedagogique,equipement_sportif,materiel_entretien,equipement_informatique,autre',
            'unite'           => 'nullable|string|max:20',
            'quantite_stock'  => 'required|integer|min:0',
            'quantite_minimum'=> 'nullable|integer|min:0',
            'etat'            => 'nullable|in:bon,use,hors_service,en_reparation',
            'valeur_unitaire' => 'nullable|numeric|min:0',
            'date_acquisition'=> 'nullable|date',
            'fournisseur'     => 'nullable|string|max:150',
            'numero_serie'    => 'nullable|string|max:100',
            'salle_id'        => 'nullable|uuid',
            'localisation'    => 'nullable|string|max:100',
            'est_immobilise'  => 'nullable|boolean',
            'note'            => 'nullable|string|max:500',
        ]);

        $article = DB::transaction(function () use ($validated) {
            $art = ArticleStock::create($validated);

            // Enregistrer le mouvement initial
            if ($art->quantite_stock > 0) {
                MouvementStock::create([
                    'tenant_id'      => config('tenant.current_id'),
                    'article_id'     => $art->id,
                    'type'           => 'entree',
                    'quantite'       => $art->quantite_stock,
                    'quantite_avant' => 0,
                    'quantite_apres' => $art->quantite_stock,
                    'motif'          => 'Stock initial',
                    'saisie_par'     => auth()->id(),
                    'date_mouvement' => today(),
                ]);
            }

            return $art;
        });

        return $this->created([
            'article'          => $article,
            'qr_code'          => $article->qr_code,
            'reference'        => $article->reference,
            'categorie_label'  => $article->categorie_label,
        ], "Article '{$article->nom}' créé · Réf : {$article->reference}");
    }

    public function show(string $id): JsonResponse
    {
        $article = ArticleStock::with([
            'mouvements' => fn($q) => $q->orderByDesc('date_mouvement')->limit(20),
            'pretsEnCours',
        ])->findOrFail($id);

        return $this->success([
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
            'valeur_totale'  => $article->valeur_totale,
            'nb_prets_cours' => $article->pretsEnCours->count(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $article   = ArticleStock::findOrFail($id);
        $validated = $request->validate([
            'nom'             => 'sometimes|string|max:150',
            'etat'            => 'sometimes|in:bon,use,hors_service,en_reparation',
            'localisation'    => 'nullable|string|max:100',
            'valeur_unitaire' => 'nullable|numeric|min:0',
            'quantite_minimum'=> 'sometimes|integer|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'est_immobilise'  => 'sometimes|boolean',
            'note'            => 'nullable|string|max:500',
        ]);

        $article->update($validated);
        return $this->success($article->fresh(), 'Article mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $article = ArticleStock::findOrFail($id);
        if ($article->pretsEnCours()->exists()) {
            return $this->error(
                'Impossible de supprimer : des prêts sont en cours',
                'HAS_PRETS', 422
            );
        }
        $nom = $article->nom;
        $article->delete();
        return $this->success(null, "Article '{$nom}' supprimé");
    }

    public function parQrCode(string $qrCode): JsonResponse
    {
        $article = ArticleStock::where('qr_code', $qrCode)->firstOrFail();
        return $this->success([
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
        ]);
    }

    // ═══════════════════════════════════════════
    // MOUVEMENTS
    // ═══════════════════════════════════════════

    public function mouvement(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type'          => 'required|in:entree,sortie,ajustement,transfert,perte',
            'quantite'      => 'required|integer|min:1',
            'motif'         => 'nullable|string|max:200',
            'reference_doc' => 'nullable|string|max:100',
            'date_mouvement'=> 'nullable|date',
        ]);

        $article = ArticleStock::findOrFail($id);
        $avant   = $article->quantite_stock;

        $apres = match ($validated['type']) {
            'entree'    => $avant + $validated['quantite'],
            'sortie',
            'perte'     => $avant - $validated['quantite'],
            'ajustement'=> $validated['quantite'],
            'transfert' => $avant - $validated['quantite'],
            default     => $avant,
        };

        if ($apres < 0) {
            return $this->error(
                "Stock insuffisant : {$avant} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
        }

        DB::transaction(function () use ($article, $validated, $avant, $apres) {
            $article->update(['quantite_stock' => $apres]);

            MouvementStock::create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => $validated['type'],
                'quantite'       => $validated['quantite'],
                'quantite_avant' => $avant,
                'quantite_apres' => $apres,
                'motif'          => $validated['motif'] ?? null,
                'reference_doc'  => $validated['reference_doc'] ?? null,
                'saisie_par'     => auth()->id(),
                'date_mouvement' => $validated['date_mouvement'] ?? today(),
            ]);
        });

        return $this->success([
            'article'       => $article->fresh(),
            'quantite_avant'=> $avant,
            'quantite_apres'=> $apres,
            'en_alerte'     => $apres <= $article->quantite_minimum,
        ], "Stock mis à jour : {$article->nom} — {$avant} → {$apres} {$article->unite}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $article   = ArticleStock::findOrFail($id);
        $paginator = MouvementStock::where('article_id', $article->id)
            ->orderByDesc('date_mouvement')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse($paginator, 'Historique mouvements', [
            'article' => ['id' => $article->id, 'nom' => $article->nom, 'stock_actuel' => $article->quantite_stock],
        ]);
    }

    // ═══════════════════════════════════════════
    // ALERTES
    // ═══════════════════════════════════════════

    public function alertes(): JsonResponse
    {
        $articles = ArticleStock::where('actif', true)
            ->enAlerte()
            ->orderBy('quantite_stock')
            ->get()
            ->map(fn($a) => [
                'article'         => $a,
                'categorie_label' => $a->categorie_label,
                'deficit'         => max(0, $a->quantite_minimum - $a->quantite_stock),
                'en_alerte'       => true,
            ]);

        return $this->success([
            'articles'   => $articles,
            'nb_alertes' => $articles->count(),
        ], "{$articles->count()} article(s) sous le seuil minimum");
    }

    // ═══════════════════════════════════════════
    // PRÊTS DE MATÉRIEL
    // ═══════════════════════════════════════════

    public function indexPrets(Request $request): JsonResponse
    {
        $paginator = PretMateriel::with('article:id,nom,reference,unite')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_pret')
            ->paginate($request->per_page ?? 20);

        // Prêts en retard
        $enRetard = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())
            ->count();

        return $this->paginatedResponse($paginator, 'Prêts récupérés', [
            'nb_en_retard' => $enRetard,
        ]);
    }

    public function creerPret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id'          => 'required|uuid|exists:articles_stock,id',
            'emprunteur_id'       => 'nullable|uuid',
            'type_emprunteur'     => 'required|in:enseignant,personnel,externe',
            'nom_emprunteur'      => 'nullable|string|max:150',
            'quantite'            => 'required|integer|min:1',
            'date_pret'           => 'nullable|date',
            'date_retour_prevue'  => 'required|date|after_or_equal:date_pret',
            'note'                => 'nullable|string|max:300',
        ]);

        $article = ArticleStock::findOrFail($validated['article_id']);

        if ($article->quantite_stock < $validated['quantite']) {
            return $this->error(
                "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
        }

        DB::transaction(function () use ($article, $validated) {
            PretMateriel::create(array_merge($validated, [
                'tenant_id'  => config('tenant.current_id'),
                'date_pret'  => $validated['date_pret'] ?? today(),
                'statut'     => 'en_cours',
            ]));

            // Déduire du stock
            $avant = $article->quantite_stock;
            $article->update(['quantite_stock' => $avant - $validated['quantite']]);

            MouvementStock::create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => 'sortie',
                'quantite'       => $validated['quantite'],
                'quantite_avant' => $avant,
                'quantite_apres' => $avant - $validated['quantite'],
                'motif'          => 'Prêt de matériel',
                'saisie_par'     => auth()->id(),
                'date_mouvement' => today(),
            ]);
        });

        return $this->created(null, "Prêt enregistré pour '{$article->nom}'");
    }

    public function retourPret(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'date_retour'  => 'nullable|date',
            'etat_retour'  => 'nullable|in:bon,use,hors_service',
            'note'         => 'nullable|string|max:300',
        ]);

        $pret    = PretMateriel::with('article')->findOrFail($id);
        $article = $pret->article;

        if ($pret->statut !== 'en_cours') {
            return $this->error('Ce prêt est déjà clôturé', 'DEJA_CLOTURE', 409);
        }

        DB::transaction(function () use ($pret, $article, $validated) {
            $pret->update([
                'statut'                 => 'rendu',
                'date_retour_effective'  => $validated['date_retour'] ?? today(),
                'note'                   => $validated['note'] ?? $pret->note,
            ]);

            // Remettre en stock
            $avant = $article->quantite_stock;
            $article->update(['quantite_stock' => $avant + $pret->quantite]);

            if (isset($validated['etat_retour'])) {
                $article->update(['etat' => $validated['etat_retour']]);
            }

            MouvementStock::create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => 'entree',
                'quantite'       => $pret->quantite,
                'quantite_avant' => $avant,
                'quantite_apres' => $avant + $pret->quantite,
                'motif'          => 'Retour de prêt',
                'saisie_par'     => auth()->id(),
                'date_mouvement' => today(),
            ]);
        });

        return $this->success(null, "Retour enregistré pour '{$article->nom}'");
    }

    // ═══════════════════════════════════════════
    // BONS DE COMMANDE
    // ═══════════════════════════════════════════

    public function indexBons(Request $request): JsonResponse
    {
        $paginator = BonCommande::with('lignes')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_commande')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse($paginator, 'Bons de commande récupérés');
    }

    public function creerBon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fournisseur'            => 'required|string|max:150',
            'fournisseur_contact'    => 'nullable|string|max:150',
            'date_commande'          => 'nullable|date',
            'date_livraison_prevue'  => 'nullable|date',
            'note'                   => 'nullable|string|max:500',
            'lignes'                 => 'required|array|min:1',
            'lignes.*.designation'   => 'required|string|max:200',
            'lignes.*.article_id'    => 'nullable|uuid',
            'lignes.*.quantite'      => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'required|numeric|min:0',
        ]);

        $bon = DB::transaction(function () use ($validated) {
            $total = collect($validated['lignes'])->sum(
                fn($l) => $l['quantite'] * $l['prix_unitaire']
            );

            $bon = BonCommande::create([
                'tenant_id'             => config('tenant.current_id'),
                'numero'                => BonCommande::genererNumero(),
                'fournisseur'           => $validated['fournisseur'],
                'fournisseur_contact'   => $validated['fournisseur_contact'] ?? null,
                'date_commande'         => $validated['date_commande'] ?? today(),
                'date_livraison_prevue' => $validated['date_livraison_prevue'] ?? null,
                'montant_total'         => $total,
                'statut'                => 'brouillon',
                'note'                  => $validated['note'] ?? null,
            ]);

            foreach ($validated['lignes'] as $ligne) {
                LigneBonCommande::create([
                    'bon_commande_id' => $bon->id,
                    'article_id'      => $ligne['article_id'] ?? null,
                    'designation'     => $ligne['designation'],
                    'quantite'        => $ligne['quantite'],
                    'prix_unitaire'   => $ligne['prix_unitaire'],
                    'total'           => $ligne['quantite'] * $ligne['prix_unitaire'],
                ]);
            }

            return $bon;
        });

        return $this->created($bon->load('lignes'), "Bon de commande {$bon->numero} créé");
    }

    public function statutBon(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut' => 'required|in:brouillon,envoye,recu,partiel,annule',
        ]);

        $bon = BonCommande::findOrFail($id);
        $bon->update(['statut' => $validated['statut']]);

        // Si reçu → mettre à jour les stocks
        if ($validated['statut'] === 'recu') {
            foreach ($bon->lignes as $ligne) {
                if ($ligne->article_id) {
                    $article = ArticleStock::find($ligne->article_id);
                    if ($article) {
                        $avant = $article->quantite_stock;
                        $article->update(['quantite_stock' => $avant + $ligne->quantite]);
                        MouvementStock::create([
                            'tenant_id'      => config('tenant.current_id'),
                            'article_id'     => $article->id,
                            'type'           => 'entree',
                            'quantite'       => $ligne->quantite,
                            'quantite_avant' => $avant,
                            'quantite_apres' => $avant + $ligne->quantite,
                            'motif'          => "Réception BC {$bon->numero}",
                            'reference_doc'  => $bon->numero,
                            'saisie_par'     => auth()->id(),
                            'date_mouvement' => today(),
                        ]);
                    }
                }
            }
        }

        return $this->success($bon->fresh('lignes'), "Bon {$bon->numero} — statut : {$validated['statut']}");
    }

    public function pdfBon(string $id)
    {
        $bon    = BonCommande::with('lignes.article')->findOrFail($id);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $pdf  = Pdf::loadView('pdf.bon_commande', compact('bon', 'tenant'))->setPaper('A4', 'portrait');
        $path = "bons_commande/{$bon->tenant_id}/{$bon->numero}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    // ═══════════════════════════════════════════
    // RAPPORT INVENTAIRE ANNUEL
    // ═══════════════════════════════════════════

    public function rapportInventaire(Request $request)
    {
        $annee  = (int) ($request->annee ?? now()->year);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $articles = ArticleStock::where('actif', true)
            ->orderBy('categorie')->orderBy('nom')
            ->get();

        $parCategorie = $articles->groupBy('categorie')->map(fn($grp) => [
            'articles'     => $grp,
            'nb_articles'  => $grp->count(),
            'valeur_totale'=> $grp->sum('valeur_totale'),
            'nb_alertes'   => $grp->filter(fn($a) => $a->en_alerte)->count(),
        ]);

        $pdf = Pdf::loadView('pdf.rapport_inventaire', [
            'articles'     => $articles,
            'par_categorie'=> $parCategorie,
            'tenant'       => $tenant,
            'annee'        => $annee,
            'date_rapport' => today()->format('d/m/Y'),
            'valeur_totale'=> $articles->sum('valeur_totale'),
            'nb_total'     => $articles->count(),
            'nb_alertes'   => $articles->filter(fn($a) => $a->en_alerte)->count(),
        ])->setPaper('A4', 'portrait');

        $path = "rapports/inventaire_{$annee}_" . now()->format('Ymd') . ".pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $alertes      = ArticleStock::where('actif', true)->enAlerte()->count();
        $pretsRetard  = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())->count();
        $bonsPendants = BonCommande::whereIn('statut', ['brouillon', 'envoye'])->count();

        $valeurTotale = ArticleStock::where('actif', true)
            ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
            ->value('total') ?? 0;

        $parCategorie = ArticleStock::where('actif', true)
            ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as qte')
            ->groupBy('categorie')->get();

        $derniersMovements = MouvementStock::with('article:id,nom')
            ->orderByDesc('created_at')->limit(5)->get();

        return $this->success([
            'alertes_stock'    => $alertes,
            'prets_en_retard'  => $pretsRetard,
            'bons_pendants'    => $bonsPendants,
            'valeur_totale_da' => (float) $valeurTotale,
            'par_categorie'    => $parCategorie,
            'derniers_mouvements' => $derniersMovements,
        ], 'Tableau de bord stock & inventaire');
    }
}
```

---

## ÉTAPE 8 — Templates PDF

### 8a — Bon de commande

**Créer :** `edugestdz/backend/resources/views/pdf/bon_commande.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 20px; color: #111; }
  .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
  .etab h2 { font-size: 15px; color: #1e40af; margin: 0; }
  .etab p  { font-size: 10px; color: #555; margin: 2px 0; }
  .titre   { text-align: center; background: #1e40af; color: #fff; padding: 10px; font-size: 14px; font-weight: bold; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { background: #e8f0fe; color: #1e40af; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
  .total-box { text-align: right; font-size: 14px; font-weight: bold; padding: 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
  .sigs { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig  { text-align: center; width: 40%; border-top: 1px solid #333; padding-top: 6px; font-size: 10px; }
</style>
</head>
<body>
<div class="header">
  <div class="etab">
    <h2>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h2>
    <p>{{ $tenant->adresse ?? '' }}</p>
    <p>NIF : {{ $tenant->nif ?? '—' }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:11px;font-weight:bold;">BON DE COMMANDE N° {{ $bon->numero }}</p>
    <p style="font-size:10px;color:#555;">Date : {{ $bon->date_commande->format('d/m/Y') }}</p>
    @if($bon->date_livraison_prevue)
    <p style="font-size:10px;color:#555;">Livraison prévue : {{ $bon->date_livraison_prevue->format('d/m/Y') }}</p>
    @endif
  </div>
</div>

<div class="titre">BON DE COMMANDE</div>

<table>
  <tr><th colspan="2">Fournisseur</th></tr>
  <tr><td><strong>{{ $bon->fournisseur }}</strong></td><td>{{ $bon->fournisseur_contact ?? '' }}</td></tr>
</table>

<table>
  <tr>
    <th>#</th>
    <th>Désignation</th>
    <th style="text-align:center">Qté</th>
    <th style="text-align:right">P.U. (DA)</th>
    <th style="text-align:right">Total (DA)</th>
  </tr>
  @foreach($bon->lignes as $i => $ligne)
  <tr>
    <td>{{ $i + 1 }}</td>
    <td>{{ $ligne->designation }}</td>
    <td style="text-align:center">{{ $ligne->quantite }}</td>
    <td style="text-align:right">{{ number_format($ligne->prix_unitaire, 2) }}</td>
    <td style="text-align:right"><strong>{{ number_format($ligne->total, 2) }}</strong></td>
  </tr>
  @endforeach
</table>

<div class="total-box">
  MONTANT TOTAL TTC : {{ number_format($bon->montant_total, 2) }} DA
</div>

@if($bon->note)
<p style="margin-top:12px;font-size:10px;color:#555;"><strong>Note :</strong> {{ $bon->note }}</p>
@endif

<div class="sigs">
  <div class="sig">Responsable des achats</div>
  <div class="sig">Fournisseur / Cachet</div>
</div>
</body>
</html>
```

### 8b — Rapport d'inventaire annuel

**Créer :** `edugestdz/backend/resources/views/pdf/rapport_inventaire.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; padding: 16px; }
  h1   { color: #1e40af; font-size: 16px; text-align: center; margin-bottom: 4px; }
  .sub { text-align: center; color: #555; font-size: 10px; margin-bottom: 16px; }
  .kpi-row { display: flex; gap: 10px; margin-bottom: 16px; }
  .kpi { flex: 1; background: #e8f0fe; border-radius: 4px; padding: 10px; text-align: center; }
  .kpi .val { font-size: 20px; font-weight: bold; color: #1e40af; }
  .kpi .lbl { font-size: 9px; color: #555; }
  h2   { color: #1e40af; font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
  th { background: #1e40af; color: #fff; padding: 5px 8px; text-align: left; }
  td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
  tr:nth-child(even) td { background: #f8fafc; }
  .alerte { color: #dc2626; font-weight: bold; }
  .footer { text-align: center; margin-top: 20px; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>

<h1>RAPPORT D'INVENTAIRE {{ $annee }}</h1>
<div class="sub">{{ $tenant->nom_etablissement ?? '' }} · Édité le {{ $date_rapport }}</div>

<div class="kpi-row">
  <div class="kpi"><div class="val">{{ $nb_total }}</div><div class="lbl">Articles recensés</div></div>
  <div class="kpi"><div class="val">{{ number_format($valeur_totale, 0, ',', ' ') }} DA</div><div class="lbl">Valeur totale estimée</div></div>
  <div class="kpi"><div class="val" style="color:{{ $nb_alertes > 0 ? '#dc2626' : '#16a34a' }}">{{ $nb_alertes }}</div><div class="lbl">Articles en alerte stock</div></div>
</div>

@foreach($par_categorie as $categorie => $data)
<h2>{{ \App\Models\ArticleStock::make(['categorie' => $categorie])->categorie_label }} ({{ $data['nb_articles'] }} articles)</h2>
<table>
  <tr>
    <th>Réf.</th>
    <th>Désignation</th>
    <th>Localisation</th>
    <th>État</th>
    <th style="text-align:center">Qté</th>
    <th style="text-align:center">Min.</th>
    <th style="text-align:right">Val. Unit.</th>
    <th style="text-align:right">Val. Totale</th>
  </tr>
  @foreach($data['articles'] as $art)
  <tr>
    <td>{{ $art->reference ?? '—' }}</td>
    <td>{{ $art->nom }}@if($art->numero_serie) <br><span style="color:#888;font-size:8px;">S/N: {{ $art->numero_serie }}</span>@endif</td>
    <td>{{ $art->localisation ?? '—' }}</td>
    <td class="{{ $art->etat === 'hors_service' ? 'alerte' : '' }}">{{ $art->etat_label }}</td>
    <td style="text-align:center" class="{{ $art->en_alerte ? 'alerte' : '' }}">{{ $art->quantite_stock }}</td>
    <td style="text-align:center">{{ $art->quantite_minimum }}</td>
    <td style="text-align:right">{{ $art->valeur_unitaire ? number_format($art->valeur_unitaire, 2) . ' DA' : '—' }}</td>
    <td style="text-align:right">{{ number_format($art->valeur_totale, 2) }} DA</td>
  </tr>
  @endforeach
  <tr style="background:#e8f0fe;font-weight:bold;">
    <td colspan="6">Sous-total {{ \App\Models\ArticleStock::make(['categorie' => $categorie])->categorie_label }}</td>
    <td></td>
    <td style="text-align:right">{{ number_format($data['valeur_totale'], 2) }} DA</td>
  </tr>
</table>
@endforeach

<div class="footer">
  EduGest DZ · Rapport inventaire {{ $annee }} · {{ $tenant->nom_etablissement ?? '' }}
</div>
</body>
</html>
```

---

## ÉTAPE 9 — Factory

**Créer :** `edugestdz/backend/database/factories/ArticleStockFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\ArticleStock;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleStockFactory extends Factory
{
    protected $model = ArticleStock::class;

    public function definition(): array
    {
        $categories = [
            'mobilier', 'fourniture_bureau', 'fourniture_pedagogique',
            'equipement_informatique', 'materiel_entretien',
        ];
        $unites = ['pièce', 'unité', 'rame', 'boîte', 'lot'];

        return [
            'nom'              => $this->faker->words(3, true),
            'categorie'        => $this->faker->randomElement($categories),
            'unite'            => $this->faker->randomElement($unites),
            'quantite_stock'   => $this->faker->numberBetween(1, 50),
            'quantite_minimum' => $this->faker->numberBetween(1, 5),
            'etat'             => $this->faker->randomElement(['bon', 'use', 'bon']),
            'valeur_unitaire'  => $this->faker->numberBetween(500, 50000),
            'est_immobilise'   => false,
            'actif'            => true,
        ];
    }

    public function enAlerte(): static
    {
        return $this->state([
            'quantite_stock'   => 0,
            'quantite_minimum' => 5,
        ]);
    }

    public function mobilier(): static
    {
        return $this->state([
            'categorie'     => 'mobilier',
            'est_immobilise'=> true,
            'unite'         => 'pièce',
        ]);
    }
}
```

---

## ÉTAPE 10 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

```php
// ── Stock & Inventaire Mobilier (M11) ──
Route::prefix('stock')->group(function () {
    Route::get('dashboard',                       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'dashboard']);
    Route::get('alertes',                         [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'alertes']);

    // Articles
    Route::get('articles',                        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'index']);
    Route::post('articles',                       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'store']);
    Route::get('articles/qr/{qr_code}',           [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'parQrCode']);
    Route::get('articles/{id}',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'show']);
    Route::put('articles/{id}',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'update']);
    Route::delete('articles/{id}',                [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'destroy']);
    Route::post('articles/{id}/mouvement',        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'mouvement']);
    Route::get('articles/{id}/historique',        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'historique']);

    // Prêts
    Route::get('prets',                           [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'indexPrets']);
    Route::post('prets',                          [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'creerPret']);
    Route::put('prets/{id}/retour',               [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'retourPret']);

    // Bons de commande
    Route::get('bons-commande',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'indexBons']);
    Route::post('bons-commande',                  [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'creerBon']);
    Route::put('bons-commande/{id}/statut',       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'statutBon']);
    Route::get('bons-commande/{id}/pdf',          [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'pdfBon']);

    // Rapport inventaire annuel
    Route::get('rapport-inventaire',              [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'rapportInventaire']);
});
```

---

## ÉTAPE 11 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/StockInventaireTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ArticleStock;
use App\Models\MouvementStock;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInventaireTest extends TestCase
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

    public function test_creer_article_avec_reference_auto(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/articles', [
                'nom'            => 'Chaise élève',
                'categorie'      => 'mobilier',
                'unite'          => 'pièce',
                'quantite_stock' => 30,
                'quantite_minimum'=> 5,
                'valeur_unitaire'=> 3500,
                'est_immobilise' => true,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['article', 'qr_code', 'reference']]);

        $this->assertDatabaseHas('articles_stock', [
            'nom'       => 'Chaise élève',
            'tenant_id' => $this->tenant->id,
        ]);

        // Mouvement initial créé automatiquement
        $this->assertDatabaseHas('mouvements_stock', [
            'type'           => 'entree',
            'quantite'       => 30,
            'quantite_avant' => 0,
            'quantite_apres' => 30,
        ]);
    }

    public function test_liste_articles_filtree_par_tenant(): void
    {
        ArticleStock::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $autreTenant = Tenant::factory()->create();
        ArticleStock::factory()->count(5)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/articles')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_trouver_article_par_qr_code(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'qr_code'   => 'ART-TEST1234',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/articles/qr/ART-TEST1234')
            ->assertStatus(200)
            ->assertJsonPath('data.article.id', $article->id);
    }

    public function test_mouvement_entree_augmente_stock(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 10,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'entree',
                'quantite' => 20,
                'motif'    => 'Livraison fournisseur',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.quantite_apres', 30);
    }

    public function test_mouvement_sortie_diminue_stock(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 15,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 5,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.quantite_apres', 10);
    }

    public function test_sortie_stock_insuffisant_bloque(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 3,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STOCK_INSUFFISANT');
    }

    public function test_alertes_articles_sous_seuil(): void
    {
        // Article en alerte
        ArticleStock::factory()->enAlerte()->create(['tenant_id' => $this->tenant->id]);
        // Article OK
        ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 20,
            'quantite_minimum'=> 5,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/alertes')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_alertes', 1);
    }

    public function test_dashboard_stock(): void
    {
        ArticleStock::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['alertes_stock', 'prets_en_retard', 'bons_pendants', 'valeur_totale_da'],
            ]);
    }

    public function test_isolation_tenant_article(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreArticle = ArticleStock::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/stock/articles/{$autreArticle->id}")
            ->assertStatus(404);
    }

    public function test_creer_bon_commande(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/bons-commande', [
                'fournisseur'    => 'Fournitures Alger SARL',
                'date_commande'  => today()->toDateString(),
                'lignes'         => [
                    ['designation' => 'Craies blanches x100', 'quantite' => 10, 'prix_unitaire' => 500],
                    ['designation' => 'Marqueurs effaçables', 'quantite' => 5,  'prix_unitaire' => 1200],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['numero', 'lignes']]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser develop avec main (attendre merge PR #5)
git checkout develop
git pull origin main

# 1. Migration
create: edugestdz/backend/database/migrations/2026_06_30_400000_create_stock_inventaire_tables.php

# 2. Modèles (dans l'ordre des dépendances)
create: edugestdz/backend/app/Models/ArticleStock.php
create: edugestdz/backend/app/Models/MouvementStock.php
create: edugestdz/backend/app/Models/PretMateriel.php
create: edugestdz/backend/app/Models/BonCommande.php
create: edugestdz/backend/app/Models/LigneBonCommande.php

# 3. Controller
create: edugestdz/backend/app/Http/Controllers/Api/V1/StockInventaireController.php

# 4. Templates PDF
create: edugestdz/backend/resources/views/pdf/bon_commande.blade.php
create: edugestdz/backend/resources/views/pdf/rapport_inventaire.blade.php

# 5. Factory
create: edugestdz/backend/database/factories/ArticleStockFactory.php

# 6. Routes
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('stock')

# 7. Tests
create: edugestdz/backend/tests/Feature/Api/StockInventaireTest.php

# 8. Migration
php artisan migrate

# 9. Tests
php artisan test --parallel
# → Attendu : 282+ précédents + 9 nouveaux = 291+ tests verts

# 10. Si tout est vert
git add .
git commit -m "feat: M11 Stock & Inventaire — Articles + Mouvements + Prêts + Bons commande + Rapport PDF + 9 tests"
git push origin develop

# 11. Ouvrir PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends merge PR #5 (Facturation intégrée) dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P3_STOCK_INVENTAIRE.md — 11 étapes dans l'ordre.

Objectif : M11 Stock & Inventaire mobilier complet.
5 tables, 5 modèles, 1 controller (18 endpoints), 2 PDF, 1 factory, 9 tests.

php artisan test --parallel → 291+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
