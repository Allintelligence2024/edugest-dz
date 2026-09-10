# PHASE2 — F3 : Paiement Fractionné (Installment Payment Plan)

## Objectif
Permettre la création de plans de paiement fractionné (tranches) pour les factures, avec relances automatiques.

---

## Étape 1 : Migration — Tables `plans_fractionnement` + `tranches_fractionnement`

### Fichier : `database/migrations/2026_07_13_700000_create_plans_fractionnement_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_fractionnement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('facture_id');
            $table->uuid('eleve_id');
            $table->integer('nb_tranches')->default(2);
            $table->decimal('montant_total', 12, 2);
            $table->string('statut', 30)->default('actif'); // actif, terminé, annulé
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('facture_id')->references('id')->on('factures');
            $table->foreign('eleve_id')->references('id')->on('eleves');
        });

        Schema::create('tranches_fractionnement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('plan_id');
            $table->integer('numero'); // 1, 2, 3...
            $table->decimal('montant', 12, 2);
            $table->date('date_echeance');
            $table->string('statut', 30)->default('en_attente'); // en_attente, payée, en_retard, annulée
            $table->decimal('montant_paye', 12, 2)->default(0);
            $table->date('date_paiement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('plan_id')->references('id')->on('plans_fractionnement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tranches_fractionnement');
        Schema::dropIfExists('plans_fractionnement');
    }
};
```

---

## Étape 2 : Modèles

### Fichier : `app/Models/PlanFractionnement.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFractionnement extends BaseModel
{
    protected $table = 'plans_fractionnement';

    protected $fillable = [
        'tenant_id',
        'facture_id',
        'eleve_id',
        'nb_tranches',
        'montant_total',
        'statut',
        'notes',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'nb_tranches' => 'integer',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tranches(): HasMany
    {
        return $this->hasMany(TrancheFractionnement::class, 'plan_id');
    }
}
```

### Fichier : `app/Models/TrancheFractionnement.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrancheFractionnement extends BaseModel
{
    protected $table = 'tranches_fractionnement';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'numero',
        'montant',
        'date_echeance',
        'statut',
        'montant_paye',
        'date_paiement',
        'notes',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'date_echeance' => 'date',
        'date_paiement' => 'date',
        'numero' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanFractionnement::class, 'plan_id');
    }
}
```

---

## Étape 3 : Méthodes à ajouter à `FacturationService.php`

Ajouter les méthodes suivantes à la **FIN** du fichier `app/Services/FacturationService.php` (après la dernière accolade de la classe, avant la fermeture).

### Méthode `creerPlanFractionnement()`

```php
/**
 * Crée un plan de fractionnement pour une facture.
 */
public function creerPlanFractionnement(array $data): PlanFractionnement
{
    return DB::transaction(function () use ($data) {
        $facture = Facture::findOrFail($data['facture_id']);
        $montantTotal = $facture->total_ttc;
        $nbTranches = $data['nb_tranches'] ?? 2;

        $plan = PlanFractionnement::create([
            'tenant_id' => config('tenant.current_id'),
            'facture_id' => $facture->id,
            'eleve_id' => $facture->eleve_id,
            'nb_tranches' => $nbTranches,
            'montant_total' => $montantTotal,
            'statut' => 'actif',
            'notes' => $data['notes'] ?? null,
        ]);

        $montantTranche = round($montantTotal / $nbTranches, 2);
        $echeances = $data['echeances'] ?? [];

        for ($i = 1; $i <= $nbTranches; $i++) {
            $montant = ($i === $nbTranches)
                ? round($montantTotal - $montantTranche * ($nbTranches - 1), 2)
                : $montantTranche;

            $dateEcheance = $echeances[$i - 1] ?? now()->addMonths($i)->toDateString();

            TrancheFractionnement::create([
                'tenant_id' => config('tenant.current_id'),
                'plan_id' => $plan->id,
                'numero' => $i,
                'montant' => $montant,
                'date_echeance' => $dateEcheance,
                'statut' => 'en_attente',
            ]);
        }

        return $plan->load('tranches');
    });
}
```

### Méthode `affecterPlanAEleve()`

```php
/**
 * Affecte un plan de fractionnement existant à un élève (copie le plan).
 */
public function affecterPlanAEleve(string $planId, string $eleveId): PlanFractionnement
{
    return DB::transaction(function () use ($planId, $eleveId) {
        $planOriginal = PlanFractionnement::with('tranches')->findOrFail($planId);

        $nouveauPlan = PlanFractionnement::create([
            'tenant_id' => config('tenant.current_id'),
            'facture_id' => $planOriginal->facture_id,
            'eleve_id' => $eleveId,
            'nb_tranches' => $planOriginal->nb_tranches,
            'montant_total' => $planOriginal->montant_total,
            'statut' => 'actif',
            'notes' => "Copie du plan " . $planOriginal->id,
        ]);

        foreach ($planOriginal->tranches as $tranche) {
            TrancheFractionnement::create([
                'tenant_id' => config('tenant.current_id'),
                'plan_id' => $nouveauPlan->id,
                'numero' => $tranche->numero,
                'montant' => $tranche->montant,
                'date_echeance' => $tranche->date_echeance,
                'statut' => 'en_attente',
            ]);
        }

        return $nouveauPlan->load('tranches');
    });
}
```

---

## Étape 4 : Controller — `PlanFractionnementController.php`

### Fichier : `app/Http/Controllers/Api/V1/PlanFractionnementController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlanFractionnement;
use App\Models\TrancheFractionnement;
use App\Services\FacturationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanFractionnementController extends Controller
{
    public function __construct(
        private FacturationService $facturationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = PlanFractionnement::with(['tranches', 'eleve', 'facture'])
            ->when($request->eleve_id, fn($q, $v) => $q->where('eleve_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facture_id' => 'required|uuid|exists:factures,id',
            'nb_tranches' => 'required|integer|min:2|max:12',
            'echeances' => 'nullable|array',
            'echeances.*' => 'date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $plan = $this->facturationService->creerPlanFractionnement($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plan de fractionnement créé avec succès',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $plan = PlanFractionnement::with(['tranches', 'eleve', 'facture'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function affecter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|uuid|exists:plans_fractionnement,id',
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ]);

        $plan = $this->facturationService->affecterPlanAEleve(
            $validated['plan_id'],
            $validated['eleve_id']
        );

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plan affecté à l\'élève avec succès',
        ], 201);
    }

    public function annuler(string $id): JsonResponse
    {
        $plan = PlanFractionnement::findOrFail($id);
        $plan->update(['statut' => 'annulé']);
        $plan->tranches()->update(['statut' => 'annulée']);

        return response()->json([
            'success' => true,
            'message' => 'Plan annulé',
        ]);
    }
}
```

---

## Étape 5 : Routes — `routes/api/finance.php`

Ajouter les routes suivantes **avant** la fermeture du fichier (après les routes existantes) :

```php
// ── Plans de fractionnement ──
Route::prefix('plans-fractionnement')->group(function () {
    Route::get('/', [PlanFractionnementController::class, 'index']);
    Route::post('/', [PlanFractionnementController::class, 'store']);
    Route::get('/{id}', [PlanFractionnementController::class, 'show']);
    Route::post('/affecter', [PlanFractionnementController::class, 'affecter']);
    Route::post('/{id}/annuler', [PlanFractionnementController::class, 'annuler']);
});
```

---

## Étape 6 : Command Artisan — `RelancesEcheanceCommand.php`

### Fichier : `app/Console/Commands/RelancesEcheanceCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\TrancheFractionnement;
use App\Services\ParentNotificationService;
use Illuminate\Console\Command;

class RelancesEcheanceCommand extends Command
{
    protected $signature = 'finance:relances-echeance';
    protected $description = 'Relance les tranches arrivant à échéance ou en retard';

    public function __construct(
        private ParentNotificationService $parentNotificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tranchesRetard = TrancheFractionnement::where('statut', 'en_attente')
            ->where('date_echeance', '<', now()->toDateString())
            ->with(['plan.eleve', 'plan.facture'])
            ->get();

        foreach ($tranchesRetard as $tranche) {
            $eleve = $tranche->plan->eleve;
            if (!$eleve) continue;

            $this->parentNotificationService->notifier(
                eleveId: $eleve->id,
                type: 'plan_paiement',
                titre: "Retard paiement tranche #{$tranche->numero}",
                corps: "La tranche #{$tranche->numero} de {$tranche->montant} DA est en retard. Échéance : {$tranche->date_echeance->format('d/m/Y')}",
                meta: [
                    'plan_id' => $tranche->plan_id,
                    'tranche_numero' => $tranche->numero,
                    'montant' => $tranche->montant,
                    'date_echeance' => $tranche->date_echeance->format('d/m/Y'),
                ]
            );

            $tranche->update(['statut' => 'en_retard']);
        }

        $this->info("Relances envoyées pour " . $tranchesRetard->count() . " tranches en retard.");
        return Command::SUCCESS;
    }
}
```

---

## Étape 7 : Tests — `PlanFractionnementTest.php`

### Fichier : `tests/Feature/Api/PlanFractionnementTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\PlanFractionnement;
use App\Models\TrancheFractionnement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFractionnementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenant.current_id' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'api');
    }

    public function test_creer_plan_fractionnement(): void
    {
        $facture = Facture::factory()->create([
            'total_ttc' => 100000,
            'statut' => 'émise',
        ]);

        $response = $this->postJson('/api/v1/plans-fractionnement', [
            'facture_id' => $facture->id,
            'nb_tranches' => 4,
            'echeances' => [
                now()->addMonth()->toDateString(),
                now()->addMonths(2)->toDateString(),
                now()->addMonths(3)->toDateString(),
                now()->addMonths(4)->toDateString(),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nb_tranches' => 4,
                    'montant_total' => 100000,
                ],
            ]);

        $this->assertDatabaseHas('plans_fractionnement', [
            'facture_id' => $facture->id,
            'nb_tranches' => 4,
        ]);

        $this->assertCount(4, TrancheFractionnement::all());
    }

    public function test_double_affectation_rejetee(): void
    {
        $facture = Facture::factory()->create(['total_ttc' => 50000]);
        $plan = PlanFractionnement::factory()->create([
            'facture_id' => $facture->id,
            'montant_total' => 50000,
        ]);

        // Double affectation → même facture même élève
        $response = $this->postJson('/api/v1/plans-fractionnement/affecter', [
            'plan_id' => $plan->id,
            'eleve_id' => $facture->eleve_id,
        ]);

        // Le plan est déjà lié à cette facture/élève → exception ou success:false
        $response->assertStatus(422);
    }

    public function test_annuler_plan(): void
    {
        $plan = PlanFractionnement::factory()->create(['statut' => 'actif']);
        TrancheFractionnement::factory()->count(3)->create([
            'plan_id' => $plan->id,
            'statut' => 'en_attente',
        ]);

        $response = $this->postJson("/api/v1/plans-fractionnement/{$plan->id}/annuler");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('plans_fractionnement', [
            'id' => $plan->id,
            'statut' => 'annulé',
        ]);
    }

    public function test_liste_plans(): void
    {
        PlanFractionnement::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/plans-fractionnement');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['data' => [['id', 'nb_tranches', 'montant_total']]],
            ]);
    }

    public function test_consulter_plan(): void
    {
        $plan = PlanFractionnement::factory()->create();
        TrancheFractionnement::factory()->count(2)->create(['plan_id' => $plan->id]);

        $response = $this->getJson("/api/v1/plans-fractionnement/{$plan->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'nb_tranches', 'montant_total', 'tranches'],
            ]);
    }

    public function test_validation_champs_requis(): void
    {
        $response = $this->postJson('/api/v1/plans-fractionnement', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['facture_id', 'nb_tranches']);
    }
}
```

### Factory pour les tests

#### `database/factories/PlanFractionnementFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\PlanFractionnement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFractionnementFactory extends Factory
{
    protected $model = PlanFractionnement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'test-tenant',
            'facture_id' => null,
            'eleve_id' => null,
            'nb_tranches' => $this->faker->randomElement([2, 3, 4]),
            'montant_total' => $this->faker->randomFloat(2, 10000, 500000),
            'statut' => 'actif',
        ];
    }
}
```

#### `database/factories/TrancheFractionnementFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\TrancheFractionnement;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrancheFractionnementFactory extends Factory
{
    protected $model = TrancheFractionnement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'test-tenant',
            'plan_id' => null,
            'numero' => $this->faker->numberBetween(1, 4),
            'montant' => $this->faker->randomFloat(2, 5000, 100000),
            'date_echeance' => $this->faker->futureDate(),
            'statut' => 'en_attente',
            'montant_paye' => 0,
        ];
    }
}
```

---

## Vérification Finale

```bash
php artisan migrate --force
php artisan test tests/Feature/Api/PlanFractionnementTest.php  # → 6 ✅
php artisan test                                                 # → ≥ 873 ✅
git add -A && git commit -m "feat: paiement fractionné (F3)"
git push origin develop
```
