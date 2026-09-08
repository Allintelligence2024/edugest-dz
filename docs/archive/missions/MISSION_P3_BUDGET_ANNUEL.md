# 🤖 MISSION DEEPSEEK — Priorité 3 : M13 Budget Annuel & Comptabilité
## EduGest DZ · Branche : develop · 29 Juin 2026
## Tests actuels : 219+ ✅ (après merge PR #2) · Objectif : 235+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE déjà (ne pas recréer)
- `app/Http/Controllers/Api/V1/FinanceController.php` — gère les **recettes** uniquement
  - `tableauBord()` → CA, impayés
  - `bilanMensuel()` / `bilanAnnuel()` → encaissements vs factures émises
  - Aucune gestion des **dépenses**
- `app/Services/FacturationService.php` — facturation, paiements, PDF
- `app/Models/Facture.php`, `Paiement.php` — modèles recettes complets
- `app/Models/Paie.php` — paies enseignants (source de dépenses existante)
- Migrations jusqu'à `2026_06_29_300000_...` → prochaine : `2026_06_29_400000`
  (M12 personnel est dans `2026_06_29_400000` → utiliser `2026_06_29_500000` pour M13)

### Ce qui MANQUE — M13 Budget Annuel complet
```
Migration  : create_budget_annuel_tables (depenses + budget_previsionnel)
Models     : Depense.php · BudgetPrevisionnel.php
Controller : BudgetController.php (nouveau, distinct de FinanceController)
Modifier   : FinanceController.php (enrichir tableauBord + bilanAnnuel)
Factory    : DepenseFactory.php
Tests      : Feature/Api/BudgetTest.php
Routes     : bloc budget dans api.php
```

### Catégories de dépenses algériennes à couvrir
```
salaires_enseignants   → liées aux Paie existantes
salaires_personnel     → liées au PersonnelNonEnseignant M12
loyer                  → mensuel fixe
electricite_gaz        → charges variables
eau                    → charges variables
telephone_internet     → charges variables
fournitures_bureau     → consommables
fournitures_pedagogiques → matériel pédagogique
maintenance_reparation → entretien bâtiment (futur M14)
assurance              → annuelle
publicite_marketing    → variable
transport              → futur M09
cantine_restauration   → futur M10
taxes_impots           → IBS, TAP, TVA
autres                 → divers
```

---

## ÉTAPE 1 — Migration

**Créer :** `edugestdz/backend/database/migrations/2026_06_29_500000_create_budget_annuel_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Dépenses réelles ──
        Schema::create('depenses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->enum('categorie', [
                'salaires_enseignants',
                'salaires_personnel',
                'loyer',
                'electricite_gaz',
                'eau',
                'telephone_internet',
                'fournitures_bureau',
                'fournitures_pedagogiques',
                'maintenance_reparation',
                'assurance',
                'publicite_marketing',
                'transport',
                'cantine_restauration',
                'taxes_impots',
                'autres',
            ]);

            $table->string('libelle', 200);           // description de la dépense
            $table->decimal('montant', 12, 2);
            $table->date('date_depense');
            $table->integer('mois');
            $table->integer('annee');
            $table->string('fournisseur', 150)->nullable();
            $table->string('numero_facture_ext', 100)->nullable(); // ref facture fournisseur
            $table->string('justificatif_url', 500)->nullable();   // scan reçu
            $table->enum('mode_paiement', ['cash', 'virement', 'cheque', 'cib'])->default('cash');
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('validee');
            $table->uuid('saisie_par')->nullable();
            $table->uuid('validee_par')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // ── Budget prévisionnel par poste/mois/année ──
        Schema::create('budget_previsionnel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->integer('annee');
            $table->integer('mois')->nullable();       // null = prévision annuelle
            $table->enum('categorie', [
                'salaires_enseignants',
                'salaires_personnel',
                'loyer',
                'electricite_gaz',
                'eau',
                'telephone_internet',
                'fournitures_bureau',
                'fournitures_pedagogiques',
                'maintenance_reparation',
                'assurance',
                'publicite_marketing',
                'transport',
                'cantine_restauration',
                'taxes_impots',
                'autres',
            ]);
            $table->decimal('montant_prevu', 12, 2);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'annee', 'mois', 'categorie'], 'budget_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_previsionnel');
        Schema::dropIfExists('depenses');
    }
};
```

---

## ÉTAPE 2 — Modèle Depense

**Créer :** `edugestdz/backend/app/Models/Depense.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'categorie', 'libelle', 'montant',
        'date_depense', 'mois', 'annee', 'fournisseur',
        'numero_facture_ext', 'justificatif_url', 'mode_paiement',
        'statut', 'saisie_par', 'validee_par', 'note',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant'      => 'decimal:2',
    ];

    // Libellé lisible de la catégorie
    public static function categorieLibelle(string $cat): string
    {
        return match ($cat) {
            'salaires_enseignants'    => 'Salaires enseignants',
            'salaires_personnel'      => 'Salaires personnel',
            'loyer'                   => 'Loyer',
            'electricite_gaz'         => 'Électricité & Gaz',
            'eau'                     => 'Eau',
            'telephone_internet'      => 'Téléphone & Internet',
            'fournitures_bureau'      => 'Fournitures bureau',
            'fournitures_pedagogiques'=> 'Fournitures pédagogiques',
            'maintenance_reparation'  => 'Maintenance & Réparation',
            'assurance'               => 'Assurance',
            'publicite_marketing'     => 'Publicité & Marketing',
            'transport'               => 'Transport',
            'cantine_restauration'    => 'Cantine & Restauration',
            'taxes_impots'            => 'Taxes & Impôts',
            default                   => 'Autres',
        };
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    public function valideePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    // Scope mois/année
    public function scopePeriode($query, int $mois, int $annee)
    {
        return $query->where('mois', $mois)->where('annee', $annee);
    }

    public function scopeAnnee($query, int $annee)
    {
        return $query->where('annee', $annee);
    }

    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }
}
```

---

## ÉTAPE 3 — Modèle BudgetPrevisionnel

**Créer :** `edugestdz/backend/app/Models/BudgetPrevisionnel.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;

class BudgetPrevisionnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'budget_previsionnel';

    protected $fillable = [
        'tenant_id', 'annee', 'mois',
        'categorie', 'montant_prevu', 'note',
    ];

    protected $casts = [
        'montant_prevu' => 'decimal:2',
    ];

    /**
     * Récupère ou crée le prévisionnel pour une catégorie/période.
     */
    public static function getPrevision(string $categorie, int $annee, ?int $mois = null): float
    {
        return (float) static::where('categorie', $categorie)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->value('montant_prevu') ?? 0.0;
    }
}
```

---

## ÉTAPE 4 — Controller BudgetController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BudgetPrevisionnel;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\Paie;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * M13 — Budget Annuel & Comptabilité complète.
 *
 * GET  /api/v1/budget/dashboard              → vue synthétique recettes/dépenses
 * GET  /api/v1/budget/depenses               → liste dépenses (filtrables)
 * POST /api/v1/budget/depenses               → saisir une dépense
 * PUT  /api/v1/budget/depenses/{id}          → modifier
 * DELETE /api/v1/budget/depenses/{id}        → supprimer (soft)
 * POST /api/v1/budget/depenses/{id}/justificatif → uploader le scan
 * GET  /api/v1/budget/previsionnel           → consulter le budget prévu
 * POST /api/v1/budget/previsionnel           → définir un budget par catégorie
 * GET  /api/v1/budget/bilan-mensuel          → recettes vs dépenses du mois
 * GET  /api/v1/budget/bilan-annuel           → bilan complet 12 mois
 * GET  /api/v1/budget/categories             → référentiel catégories
 */
class BudgetController extends BaseApiController
{
    // ── Dashboard global ────────────────────────────
    public function dashboard(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);

        // Recettes du mois (paiements confirmés)
        $recettes = (float) Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        // Dépenses du mois (validées)
        $depenses = (float) Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        // Résultat net
        $resultatNet = $recettes - $depenses;

        // Dépenses par catégorie
        $parCategorie = Depense::validees()
            ->periode($mois, $annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->mapWithKeys(fn($r) => [
                $r->categorie => [
                    'libelle' => Depense::categorieLibelle($r->categorie),
                    'total'   => (float) $r->total,
                    'prevu'   => BudgetPrevisionnel::getPrevision($r->categorie, $annee, $mois),
                ],
            ]);

        // Impayés
        $impayes = (float) Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
            ->where('date_echeance', '<', today())
            ->sum('total_ttc');

        // Évolution 6 derniers mois
        $evolution = [];
        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $m     = $date->month;
            $a     = $date->year;
            $rec   = (float) Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $m)->whereYear('date_paiement', $a)->sum('montant');
            $dep   = (float) Depense::validees()->periode($m, $a)->sum('montant');
            $evolution[] = [
                'label'     => $date->translatedFormat('M Y'),
                'recettes'  => $rec,
                'depenses'  => $dep,
                'resultat'  => $rec - $dep,
            ];
        }

        return $this->success([
            'periode'       => compact('mois', 'annee'),
            'recettes'      => $recettes,
            'depenses'      => $depenses,
            'resultat_net'  => $resultatNet,
            'impayes'       => $impayes,
            'par_categorie' => $parCategorie,
            'evolution'     => $evolution,
        ], "Dashboard budget {$mois}/{$annee}");
    }

    // ── Liste des dépenses ───────────────────────────
    public function indexDepenses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'      => 'nullable|integer|min:1|max:12',
            'annee'     => 'nullable|integer|min:2020|max:2030',
            'categorie' => 'nullable|string',
            'statut'    => 'nullable|in:en_attente,validee,rejetee',
            'search'    => 'nullable|string|max:100',
            'per_page'  => 'nullable|integer|min:5|max:100',
        ]);

        $query = Depense::with('saisiePar:id,nom,prenom')
            ->orderByDesc('date_depense');

        if (!empty($validated['mois']) && !empty($validated['annee'])) {
            $query->periode($validated['mois'], $validated['annee']);
        } elseif (!empty($validated['annee'])) {
            $query->annee($validated['annee']);
        }

        if (!empty($validated['categorie'])) {
            $query->where('categorie', $validated['categorie']);
        }
        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('libelle', 'ILIKE', "%{$validated['search']}%")
                  ->orWhere('fournisseur', 'ILIKE', "%{$validated['search']}%");
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 20);

        // Total de la sélection
        $totalSelection = Depense::when(!empty($validated['mois']) && !empty($validated['annee']),
            fn($q) => $q->periode($validated['mois'], $validated['annee'])
        )->when(!empty($validated['categorie']),
            fn($q) => $q->where('categorie', $validated['categorie'])
        )->validees()->sum('montant');

        return $this->paginatedResponse($paginator, 'Dépenses récupérées', [
            'total_selection' => (float) $totalSelection,
        ]);
    }

    // ── Saisir une dépense ───────────────────────────
    public function storeDepense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categorie'          => 'required|in:salaires_enseignants,salaires_personnel,loyer,electricite_gaz,eau,telephone_internet,fournitures_bureau,fournitures_pedagogiques,maintenance_reparation,assurance,publicite_marketing,transport,cantine_restauration,taxes_impots,autres',
            'libelle'            => 'required|string|max:200',
            'montant'            => 'required|numeric|min:0.01',
            'date_depense'       => 'required|date',
            'fournisseur'        => 'nullable|string|max:150',
            'numero_facture_ext' => 'nullable|string|max:100',
            'mode_paiement'      => 'nullable|in:cash,virement,cheque,cib',
            'note'               => 'nullable|string|max:500',
        ]);

        $date = \Carbon\Carbon::parse($validated['date_depense']);
        $validated['mois']      = $date->month;
        $validated['annee']     = $date->year;
        $validated['saisie_par']= auth()->id();
        $validated['statut']    = 'validee';

        $depense = Depense::create($validated);

        return $this->created([
            'depense'  => $depense,
            'categorie_libelle' => Depense::categorieLibelle($depense->categorie),
        ], "Dépense enregistrée : {$depense->libelle} — " . number_format($depense->montant, 2) . " DA");
    }

    // ── Modifier une dépense ─────────────────────────
    public function updateDepense(Request $request, string $id): JsonResponse
    {
        $depense   = Depense::findOrFail($id);
        $validated = $request->validate([
            'categorie'          => 'sometimes|in:salaires_enseignants,salaires_personnel,loyer,electricite_gaz,eau,telephone_internet,fournitures_bureau,fournitures_pedagogiques,maintenance_reparation,assurance,publicite_marketing,transport,cantine_restauration,taxes_impots,autres',
            'libelle'            => 'sometimes|string|max:200',
            'montant'            => 'sometimes|numeric|min:0.01',
            'date_depense'       => 'sometimes|date',
            'fournisseur'        => 'nullable|string|max:150',
            'mode_paiement'      => 'nullable|in:cash,virement,cheque,cib',
            'statut'             => 'sometimes|in:en_attente,validee,rejetee',
            'note'               => 'nullable|string|max:500',
        ]);

        if (isset($validated['date_depense'])) {
            $date = \Carbon\Carbon::parse($validated['date_depense']);
            $validated['mois']  = $date->month;
            $validated['annee'] = $date->year;
        }

        $depense->update($validated);

        return $this->success($depense->fresh(), 'Dépense mise à jour');
    }

    // ── Supprimer ────────────────────────────────────
    public function destroyDepense(string $id): JsonResponse
    {
        $depense  = Depense::findOrFail($id);
        $libelle  = $depense->libelle;
        $depense->delete();

        return $this->success(null, "Dépense '{$libelle}' supprimée");
    }

    // ── Upload justificatif ──────────────────────────
    public function uploadJustificatif(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'justificatif' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $depense = Depense::findOrFail($id);
        $path    = $request->file('justificatif')->store(
            'depenses/' . config('tenant.current_id'),
            'public'
        );

        $depense->update(['justificatif_url' => $path]);

        return $this->success(
            ['justificatif_url' => $path],
            'Justificatif uploadé'
        );
    }

    // ── Budget prévisionnel : consulter ─────────────
    public function previsionnel(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);
        $mois  = $request->filled('mois') ? (int) $request->mois : null;

        $previsions = BudgetPrevisionnel::where('annee', $annee)
            ->where('mois', $mois)
            ->get()
            ->keyBy('categorie');

        $categories = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'eau', 'telephone_internet',
            'fournitures_bureau', 'fournitures_pedagogiques',
            'maintenance_reparation', 'assurance', 'publicite_marketing',
            'transport', 'cantine_restauration', 'taxes_impots', 'autres',
        ];

        $data = collect($categories)->map(function (string $cat) use ($previsions, $annee, $mois) {
            $prevu    = (float) ($previsions[$cat]?->montant_prevu ?? 0);
            $realise  = (float) Depense::validees()
                ->where('categorie', $cat)
                ->where('annee', $annee)
                ->when($mois, fn($q) => $q->where('mois', $mois))
                ->sum('montant');

            return [
                'categorie' => $cat,
                'libelle'   => Depense::categorieLibelle($cat),
                'prevu'     => $prevu,
                'realise'   => $realise,
                'ecart'     => $prevu - $realise,
                'pct_realise' => $prevu > 0 ? round(($realise / $prevu) * 100, 1) : null,
            ];
        });

        return $this->success([
            'annee'           => $annee,
            'mois'            => $mois,
            'lignes'          => $data,
            'total_prevu'     => $data->sum('prevu'),
            'total_realise'   => $data->sum('realise'),
            'ecart_total'     => $data->sum('ecart'),
        ], "Budget prévisionnel {$annee}");
    }

    // ── Budget prévisionnel : définir ───────────────
    public function setPrevisionnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'annee'     => 'required|integer|min:2020|max:2030',
            'mois'      => 'nullable|integer|min:1|max:12',
            'lignes'    => 'required|array|min:1',
            'lignes.*.categorie'     => 'required|string',
            'lignes.*.montant_prevu' => 'required|numeric|min:0',
            'lignes.*.note'          => 'nullable|string|max:300',
        ]);

        $enregistres = 0;
        foreach ($validated['lignes'] as $ligne) {
            BudgetPrevisionnel::updateOrCreate(
                [
                    'tenant_id' => config('tenant.current_id'),
                    'annee'     => $validated['annee'],
                    'mois'      => $validated['mois'] ?? null,
                    'categorie' => $ligne['categorie'],
                ],
                [
                    'montant_prevu' => $ligne['montant_prevu'],
                    'note'          => $ligne['note'] ?? null,
                ]
            );
            $enregistres++;
        }

        return $this->success(
            ['enregistres' => $enregistres],
            "{$enregistres} ligne(s) de budget enregistrée(s)"
        );
    }

    // ── Bilan mensuel recettes vs dépenses ───────────
    public function bilanMensuel(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);

        $recettes = (float) Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $depenses = (float) Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        $depensesDetail = Depense::validees()
            ->periode($mois, $annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->map(fn($r) => [
                'categorie' => $r->categorie,
                'libelle'   => Depense::categorieLibelle($r->categorie),
                'total'     => (float) $r->total,
            ]);

        $facturesEmises = (float) Facture::whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->sum('total_ttc');

        return $this->success([
            'periode'         => compact('mois', 'annee'),
            'recettes'        => $recettes,
            'factures_emises' => $facturesEmises,
            'depenses'        => $depenses,
            'resultat_net'    => $recettes - $depenses,
            'taux_recouvrement' => $facturesEmises > 0
                ? round(($recettes / $facturesEmises) * 100, 1) : 0,
            'depenses_detail' => $depensesDetail,
        ], "Bilan {$mois}/{$annee}");
    }

    // ── Bilan annuel complet ─────────────────────────
    public function bilanAnnuel(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);

        $data = [];
        $totalRecettes = 0;
        $totalDepenses = 0;

        for ($m = 1; $m <= 12; $m++) {
            $rec = (float) Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $m)
                ->whereYear('date_paiement', $annee)
                ->sum('montant');

            $dep = (float) Depense::validees()
                ->periode($m, $annee)
                ->sum('montant');

            $totalRecettes += $rec;
            $totalDepenses += $dep;

            $data[] = [
                'mois'        => $m,
                'label'       => \Carbon\Carbon::create($annee, $m, 1)->translatedFormat('F'),
                'recettes'    => $rec,
                'depenses'    => $dep,
                'resultat'    => $rec - $dep,
            ];
        }

        // Détail dépenses annuelles par catégorie
        $depensesParCategorie = Depense::validees()
            ->annee($annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->map(fn($r) => [
                'categorie' => $r->categorie,
                'libelle'   => Depense::categorieLibelle($r->categorie),
                'total'     => (float) $r->total,
                'pct'       => $totalDepenses > 0
                    ? round(($r->total / $totalDepenses) * 100, 1) : 0,
            ]);

        return $this->success([
            'annee'                   => $annee,
            'mois_par_mois'           => $data,
            'total_recettes'          => $totalRecettes,
            'total_depenses'          => $totalDepenses,
            'resultat_annuel'         => $totalRecettes - $totalDepenses,
            'depenses_par_categorie'  => $depensesParCategorie,
        ], "Bilan annuel {$annee}");
    }

    // ── Référentiel catégories ───────────────────────
    public function categories(): JsonResponse
    {
        $cats = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'eau', 'telephone_internet',
            'fournitures_bureau', 'fournitures_pedagogiques',
            'maintenance_reparation', 'assurance', 'publicite_marketing',
            'transport', 'cantine_restauration', 'taxes_impots', 'autres',
        ];

        return $this->success(
            collect($cats)->map(fn($c) => [
                'code'    => $c,
                'libelle' => Depense::categorieLibelle($c),
            ]),
            'Catégories de dépenses'
        );
    }
}
```

---

## ÉTAPE 5 — Factory

**Créer :** `edugestdz/backend/database/factories/DepenseFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Depense;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepenseFactory extends Factory
{
    protected $model = Depense::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-6 months', 'now');
        $categories = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'fournitures_bureau', 'assurance', 'autres',
        ];

        return [
            'categorie'    => $this->faker->randomElement($categories),
            'libelle'      => $this->faker->sentence(4),
            'montant'      => $this->faker->randomFloat(2, 1000, 150000),
            'date_depense' => $date->format('Y-m-d'),
            'mois'         => (int) $date->format('m'),
            'annee'        => (int) $date->format('Y'),
            'fournisseur'  => $this->faker->company(),
            'mode_paiement'=> $this->faker->randomElement(['cash', 'virement', 'cheque']),
            'statut'       => 'validee',
        ];
    }

    public function loyer(): static
    {
        return $this->state([
            'categorie' => 'loyer',
            'libelle'   => 'Loyer mensuel local commercial',
            'montant'   => 80000,
        ]);
    }

    public function salaires(): static
    {
        return $this->state([
            'categorie' => 'salaires_enseignants',
            'libelle'   => 'Paie mensuelle enseignants',
        ]);
    }
}
```

---

## ÉTAPE 6 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter dans le groupe `middleware(['auth:api', 'resolve.tenant', 'check.subscription'])` :

```php
// ── Budget Annuel & Comptabilité (M13) ──
Route::prefix('budget')->group(function () {
    Route::get('dashboard',                  [\App\Http\Controllers\Api\V1\BudgetController::class, 'dashboard']);
    Route::get('categories',                 [\App\Http\Controllers\Api\V1\BudgetController::class, 'categories']);
    Route::get('bilan-mensuel',              [\App\Http\Controllers\Api\V1\BudgetController::class, 'bilanMensuel']);
    Route::get('bilan-annuel',               [\App\Http\Controllers\Api\V1\BudgetController::class, 'bilanAnnuel']);

    // Dépenses
    Route::get('depenses',                   [\App\Http\Controllers\Api\V1\BudgetController::class, 'indexDepenses']);
    Route::post('depenses',                  [\App\Http\Controllers\Api\V1\BudgetController::class, 'storeDepense']);
    Route::put('depenses/{id}',              [\App\Http\Controllers\Api\V1\BudgetController::class, 'updateDepense']);
    Route::delete('depenses/{id}',           [\App\Http\Controllers\Api\V1\BudgetController::class, 'destroyDepense']);
    Route::post('depenses/{id}/justificatif',[\App\Http\Controllers\Api\V1\BudgetController::class, 'uploadJustificatif']);

    // Prévisionnel
    Route::get('previsionnel',               [\App\Http\Controllers\Api\V1\BudgetController::class, 'previsionnel']);
    Route::post('previsionnel',              [\App\Http\Controllers\Api\V1\BudgetController::class, 'setPrevisionnel']);
});
```

---

## ÉTAPE 7 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/BudgetTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\BudgetPrevisionnel;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
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

    // ─── CATÉGORIES ──────────────────────────────────

    public function test_categories_retourne_liste(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/categories')
            ->assertStatus(200)
            ->assertJsonCount(15, 'data');
    }

    // ─── DÉPENSES CRUD ───────────────────────────────

    public function test_creer_depense_valide(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/depenses', [
                'categorie'    => 'loyer',
                'libelle'      => 'Loyer juin 2026',
                'montant'      => 80000,
                'date_depense' => '2026-06-01',
                'fournisseur'  => 'Propriétaire',
                'mode_paiement'=> 'virement',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.depense.categorie', 'loyer');

        $this->assertDatabaseHas('depenses', [
            'libelle'   => 'Loyer juin 2026',
            'montant'   => 80000,
            'tenant_id' => $this->tenant->id,
            'mois'      => 6,
            'annee'     => 2026,
        ]);
    }

    public function test_liste_depenses_filtree_par_tenant(): void
    {
        Depense::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'mois'      => now()->month,
            'annee'     => now()->year,
        ]);

        $autreTenant = Tenant::factory()->create();
        Depense::factory()->count(5)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/budget/depenses')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_modifier_depense(): void
    {
        $depense = Depense::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/budget/depenses/{$depense->id}", [
                'montant' => 99999,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.montant', '99999.00');
    }

    public function test_supprimer_depense(): void
    {
        $depense = Depense::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/budget/depenses/{$depense->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('depenses', ['id' => $depense->id]);
    }

    public function test_isolation_tenant_depenses(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreDepense = Depense::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/budget/depenses/{$autreDepense->id}", ['montant' => 1])
            ->assertStatus(404);
    }

    // ─── PRÉVISIONNEL ────────────────────────────────

    public function test_definir_budget_previsionnel(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/previsionnel', [
                'annee' => 2026,
                'mois'  => 7,
                'lignes'=> [
                    ['categorie' => 'loyer',              'montant_prevu' => 80000],
                    ['categorie' => 'salaires_enseignants','montant_prevu' => 500000],
                    ['categorie' => 'electricite_gaz',    'montant_prevu' => 15000],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.enregistres', 3);

        $this->assertDatabaseHas('budget_previsionnel', [
            'categorie'     => 'loyer',
            'montant_prevu' => 80000,
            'annee'         => 2026,
            'mois'          => 7,
            'tenant_id'     => $this->tenant->id,
        ]);
    }

    public function test_consulter_previsionnel_avec_ecart(): void
    {
        // Définir un budget
        BudgetPrevisionnel::create([
            'tenant_id'     => $this->tenant->id,
            'annee'         => now()->year,
            'mois'          => now()->month,
            'categorie'     => 'loyer',
            'montant_prevu' => 80000,
        ]);

        // Saisir une dépense réelle
        Depense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'categorie' => 'loyer',
            'montant'   => 75000,
            'mois'      => now()->month,
            'annee'     => now()->year,
            'statut'    => 'validee',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/budget/previsionnel?annee=' . now()->year . '&mois=' . now()->month)
            ->assertStatus(200);

        $loyer = collect($response->json('data.lignes'))
            ->firstWhere('categorie', 'loyer');

        $this->assertEquals(80000, $loyer['prevu']);
        $this->assertEquals(75000, $loyer['realise']);
        $this->assertEquals(5000,  $loyer['ecart']);
    }

    // ─── BILAN MENSUEL ───────────────────────────────

    public function test_bilan_mensuel_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/bilan-mensuel?mois=' . now()->month . '&annee=' . now()->year)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'periode', 'recettes', 'factures_emises',
                    'depenses', 'resultat_net',
                    'taux_recouvrement', 'depenses_detail',
                ],
            ]);
    }

    public function test_bilan_mensuel_calcul_resultat(): void
    {
        $mois  = now()->month;
        $annee = now()->year;

        // Créer une dépense
        Depense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'montant'   => 50000,
            'mois'      => $mois,
            'annee'     => $annee,
            'statut'    => 'validee',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/budget/bilan-mensuel?mois={$mois}&annee={$annee}")
            ->assertStatus(200);

        $this->assertEquals(50000, $response->json('data.depenses'));
        // résultat = recettes(0) - dépenses(50000) = -50000
        $this->assertEquals(-50000, $response->json('data.resultat_net'));
    }

    // ─── BILAN ANNUEL ────────────────────────────────

    public function test_bilan_annuel_12_mois(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/budget/bilan-annuel?annee=' . now()->year)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'annee', 'mois_par_mois',
                    'total_recettes', 'total_depenses', 'resultat_annuel',
                    'depenses_par_categorie',
                ],
            ]);

        $this->assertCount(12, $response->json('data.mois_par_mois'));
    }

    // ─── DASHBOARD ───────────────────────────────────

    public function test_dashboard_budget_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'periode', 'recettes', 'depenses',
                    'resultat_net', 'impayes',
                    'par_categorie', 'evolution',
                ],
            ]);
    }

    public function test_dashboard_evolution_6_mois(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(200);

        $this->assertCount(6, $response->json('data.evolution'));
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre que PR #2 (M12 Personnel) soit mergée dans main
#    Puis synchroniser develop
git checkout develop
git pull origin main

# 1. Créer la migration
create: edugestdz/backend/database/migrations/2026_06_29_500000_create_budget_annuel_tables.php

# 2. Créer les modèles
create: edugestdz/backend/app/Models/Depense.php
create: edugestdz/backend/app/Models/BudgetPrevisionnel.php

# 3. Créer le controller
create: edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php

# 4. Créer la factory
create: edugestdz/backend/database/factories/DepenseFactory.php

# 5. Ajouter les routes dans api.php
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('budget') dans le groupe auth:api

# 6. Créer les tests
create: edugestdz/backend/tests/Feature/Api/BudgetTest.php

# 7. Lancer la migration
php artisan migrate

# 8. Lancer les tests
php artisan test --parallel
# → Attendu : tests précédents + 13 nouveaux = 232+ tests verts

# 9. Si tout est vert
git add .
git commit -m "feat: M13 Budget Annuel — Dépenses + Prévisionnel + Bilan + 13 tests"
git push origin develop

# 10. Ouvrir PR develop → main sur GitHub
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends que PR #2 (M12 Personnel) soit mergée dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P3_BUDGET_ANNUEL.md
Exécute les 10 étapes dans l'ordre.
php artisan test --parallel → 232+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
