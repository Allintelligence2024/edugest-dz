# 🤖 MISSION DEEPSEEK — Priorité 2 : Intégrations Facturation
## Transport + Cantine → Facture mensuelle élève
## EduGest DZ · Branche : develop · 30 Juin 2026
## Tests actuels : 275+ ✅ · Objectif : 290+ ✅

---

## CONTEXTE EXACT

### Le problème
M09 (Transport) et M10 (Cantine) existent et fonctionnent.
Mais leurs tarifs **ne sont pas ajoutés à la facture mensuelle** de l'élève.
Un directeur doit facturer manuellement — ce qui tue la proposition de valeur.

### Ce qui EXISTE (ne pas recréer)
- `app/Services/FacturationService.php` → méthode `creerFacture(array $data)` complète
  - Crée une facture + lignes via `$data['lignes']` array
  - `LigneFacture` a `type_ligne` : `'cours'`, à étendre avec `'transport'`, `'cantine'`
- `app/Models/TransportEleve.php` → `tarif_mensuel_applique`, `actif`, `eleve_id`, `circuit_id`
- `app/Models/InscriptionCantine.php` → `tarif_mensuel`, `actif`, `eleve_id`
- `app/Models/Eleve.php` → `paiements()`, `factures()`, `inscriptions()`
- `app/Models/LigneFacture.php` → `type_ligne`, `description`, `prix_unitaire`, `total`
- Routes `/api/v1/factures/*` → déjà déclarées ✅

### Ce qui MANQUE — à créer
```
1. FacturationService → 2 nouvelles méthodes
2. FactureController → 2 nouvelles routes
3. Command Artisan → génération automatique mensuelle
4. Job → génération asynchrone par queue
5. Tests → FacturationIntegrationTest.php
```

---

## ÉTAPE 1 — Étendre FacturationService

**Modifier :** `edugestdz/backend/app/Services/FacturationService.php`

Ajouter ces 3 méthodes à la fin de la classe (avant la dernière `}`).

### Méthode 1 — Génération facture mensuelle complète d'un élève

```php
/**
 * Génère la facture mensuelle complète d'un élève :
 * scolarité + transport (si inscrit) + cantine (si inscrit).
 * Évite les doublons : ne génère pas si déjà facturé pour ce mois.
 */
public function genererFactureMensuelleEleve(
    string $eleveId,
    int $mois,
    int $annee,
    float $tarifScolarite = 0
): ?Facture {
    $eleve     = \App\Models\Eleve::findOrFail($eleveId);
    $tenantId  = config('tenant.current_id');

    // ── Vérifier qu'une facture n'existe pas déjà ce mois ──
    $dejaFacturee = Facture::where('eleve_id', $eleveId)
        ->where('mois', $mois)
        ->where('annee', $annee)
        ->exists();

    if ($dejaFacturee) {
        return null; // idempotent — ne pas doubler
    }

    $lignes = [];

    // ── Ligne scolarité (si tarif > 0) ──
    if ($tarifScolarite > 0) {
        $lignes[] = [
            'description'   => "Frais de scolarité — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
            'quantite'      => 1,
            'prix_unitaire' => $tarifScolarite,
            'total'         => $tarifScolarite,
            'type_ligne'    => 'cours',
        ];
    }

    // ── Ligne transport (si inscrit et actif) ──
    $transport = \App\Models\TransportEleve::where('eleve_id', $eleveId)
        ->where('actif', true)
        ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()))
        ->with('circuit:id,nom')
        ->first();

    if ($transport && $transport->tarif_mensuel_applique > 0) {
        $lignes[] = [
            'description'   => "Transport scolaire — {$transport->circuit->nom} — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
            'quantite'      => 1,
            'prix_unitaire' => $transport->tarif_mensuel_applique,
            'total'         => $transport->tarif_mensuel_applique,
            'type_ligne'    => 'transport',
        ];
    }

    // ── Ligne cantine (si inscrit et actif) ──
    $cantine = \App\Models\InscriptionCantine::where('eleve_id', $eleveId)
        ->where('actif', true)
        ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()))
        ->first();

    if ($cantine && $cantine->tarif_mensuel > 0) {
        // Pour abonnement journalier : compter les repas réels du mois
        if ($cantine->type_abonnement === 'journalier') {
            $nbRepas = \App\Models\RepasJournalier::where('eleve_id', $eleveId)
                ->where('present', true)
                ->whereMonth('date_repas', $mois)
                ->whereYear('date_repas', $annee)
                ->count();

            if ($nbRepas > 0) {
                $prixUnitaire = $cantine->tarif_mensuel; // ici = prix par repas
                $total        = $nbRepas * $prixUnitaire;
                $lignes[]     = [
                    'description'   => "Cantine ({$nbRepas} repas) — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                    'quantite'      => $nbRepas,
                    'prix_unitaire' => $prixUnitaire,
                    'total'         => $total,
                    'type_ligne'    => 'cantine',
                ];
            }
        } else {
            // Forfait mensuel
            $lignes[] = [
                'description'   => "Cantine (forfait mensuel) — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                'quantite'      => 1,
                'prix_unitaire' => $cantine->tarif_mensuel,
                'total'         => $cantine->tarif_mensuel,
                'type_ligne'    => 'cantine',
            ];
        }
    }

    // ── Si aucune ligne → pas de facture ──
    if (empty($lignes)) {
        return null;
    }

    // ── Créer la facture via la méthode existante ──
    return $this->creerFacture([
        'eleve_id'      => $eleveId,
        'mois'          => $mois,
        'annee'         => $annee,
        'date_emission' => today()->toDateString(),
        'date_echeance' => today()->addDays(15)->toDateString(),
        'lignes'        => $lignes,
        'notes'         => "Facture mensuelle auto-générée",
    ]);
}

/**
 * Génère les factures mensuelles de TOUS les élèves actifs du tenant.
 * Utilisé par la commande Artisan mensuelle.
 */
public function genererFacturesMensuelles(int $mois, int $annee, float $tarifScolariteDefaut = 0): array
{
    $eleves  = \App\Models\Eleve::actifs()->get();
    $resultats = ['generees' => 0, 'ignorees' => 0, 'erreurs' => []];

    foreach ($eleves as $eleve) {
        try {
            $facture = $this->genererFactureMensuelleEleve(
                $eleve->id,
                $mois,
                $annee,
                $tarifScolariteDefaut
            );

            if ($facture) {
                $resultats['generees']++;
            } else {
                $resultats['ignorees']++; // déjà facturé ou aucune ligne
            }
        } catch (\Throwable $e) {
            $resultats['erreurs'][] = [
                'eleve'  => $eleve->nom_complet,
                'erreur' => $e->getMessage(),
            ];
        }
    }

    return $resultats;
}
```

---

## ÉTAPE 2 — Ajouter type_ligne 'transport' et 'cantine' dans LigneFacture

**Modifier :** `edugestdz/backend/app/Models/LigneFacture.php`

Ajouter un accesseur pour le libellé du type :

```php
public function getTypeLabelAttribute(): string
{
    return match ($this->type_ligne) {
        'cours'     => 'Scolarité',
        'transport' => 'Transport scolaire',
        'cantine'   => 'Cantine / Restauration',
        default     => ucfirst($this->type_ligne),
    };
}
```

---

## ÉTAPE 3 — Nouvelles routes dans api.php

**Modifier :** `edugestdz/backend/routes/api.php`

Dans le bloc `Route::prefix('factures')` existant, ajouter :

```php
// Génération mensuelle
Route::post('generer-mensuelle',      [\App\Http\Controllers\Api\V1\FactureController::class, 'genererMensuelle']);
Route::post('generer-toutes',         [\App\Http\Controllers\Api\V1\FactureController::class, 'genererToutes']);
```

---

## ÉTAPE 4 — Nouvelles méthodes dans FactureController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/FactureController.php`

Ajouter ces 2 méthodes à la fin de la classe :

```php
/**
 * Générer la facture mensuelle d'un élève (scolarité + transport + cantine)
 * POST /api/v1/factures/generer-mensuelle
 */
public function genererMensuelle(Request $request): JsonResponse
{
    $validated = $request->validate([
        'eleve_id'          => 'required|uuid|exists:eleves,id',
        'mois'              => 'required|integer|between:1,12',
        'annee'             => 'required|integer|min:2020',
        'tarif_scolarite'   => 'nullable|numeric|min:0',
    ]);

    $facture = $this->service->genererFactureMensuelleEleve(
        $validated['eleve_id'],
        $validated['mois'],
        $validated['annee'],
        $validated['tarif_scolarite'] ?? 0
    );

    if (!$facture) {
        return response()->json([
            'success' => false,
            'message' => 'Facture déjà existante pour ce mois ou aucune ligne à facturer',
            'code'    => 'DEJA_FACTUREE',
        ], 409);
    }

    return response()->json([
        'success' => true,
        'message' => "Facture {$facture->numero_facture} générée",
        'data'    => $facture->load('lignes', 'eleve'),
    ], 201);
}

/**
 * Générer les factures mensuelles de tous les élèves actifs
 * POST /api/v1/factures/generer-toutes
 */
public function genererToutes(Request $request): JsonResponse
{
    $validated = $request->validate([
        'mois'            => 'required|integer|between:1,12',
        'annee'           => 'required|integer|min:2020',
        'tarif_scolarite' => 'nullable|numeric|min:0',
    ]);

    // Dispatcher un job en queue pour éviter le timeout HTTP
    \App\Jobs\GenererFacturesMensuelles::dispatch(
        $validated['mois'],
        $validated['annee'],
        $validated['tarif_scolarite'] ?? 0,
        config('tenant.current_id')
    );

    return response()->json([
        'success' => true,
        'message' => "Génération des factures de {$validated['mois']}/{$validated['annee']} lancée en arrière-plan",
        'data'    => [
            'mois'  => $validated['mois'],
            'annee' => $validated['annee'],
            'statut'=> 'en_cours',
        ],
    ]);
}
```

---

## ÉTAPE 5 — Job de génération asynchrone

**Créer :** `edugestdz/backend/app/Jobs/GenererFacturesMensuelles.php`

```php
<?php

namespace App\Jobs;

use App\Services\FacturationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenererFacturesMensuelles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max
    public int $tries   = 1;   // pas de retry — idempotent

    public function __construct(
        public readonly int    $mois,
        public readonly int    $annee,
        public readonly float  $tarifScolarite,
        public readonly string $tenantId,
    ) {}

    public function handle(FacturationService $service): void
    {
        // Restaurer le contexte tenant dans le job
        config(['tenant.current_id' => $this->tenantId]);

        $resultats = $service->genererFacturesMensuelles(
            $this->mois,
            $this->annee,
            $this->tarifScolarite
        );

        Log::info("GenererFacturesMensuelles terminé", [
            'mois'      => $this->mois,
            'annee'     => $this->annee,
            'tenant_id' => $this->tenantId,
            'resultats' => $resultats,
        ]);

        if (!empty($resultats['erreurs'])) {
            Log::warning("Erreurs lors de la génération de factures", [
                'erreurs' => $resultats['erreurs'],
            ]);
        }
    }
}
```

---

## ÉTAPE 6 — Command Artisan mensuelle

**Créer :** `edugestdz/backend/app/Console/Commands/GenererFacturesMois.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FacturationService;
use Illuminate\Console\Command;

class GenererFacturesMois extends Command
{
    protected $signature   = 'factures:generer-mensuel
                              {--mois= : Mois (1-12), défaut = mois précédent}
                              {--annee= : Année, défaut = année courante}
                              {--tarif= : Tarif scolarité mensuel en DA (défaut 0)}';

    protected $description = 'Génère les factures mensuelles pour tous les élèves actifs (scolarité + transport + cantine)';

    public function handle(FacturationService $service): int
    {
        $mois  = (int) ($this->option('mois')  ?? now()->subMonth()->month);
        $annee = (int) ($this->option('annee') ?? now()->year);
        $tarif = (float) ($this->option('tarif') ?? 0);

        $this->info("Génération des factures {$mois}/{$annee} pour tous les tenants actifs...");

        $totalGenerees = 0;
        $totalIgnorees = 0;

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use (
            $service, $mois, $annee, $tarif, &$totalGenerees, &$totalIgnorees
        ) {
            config(['tenant.current_id' => $tenant->id]);

            $this->line("  → {$tenant->nom_etablissement}");

            $resultats = $service->genererFacturesMensuelles($mois, $annee, $tarif);

            $totalGenerees += $resultats['generees'];
            $totalIgnorees += $resultats['ignorees'];

            $this->line("    ✅ {$resultats['generees']} générée(s) · ⏭ {$resultats['ignorees']} ignorée(s)");

            if (!empty($resultats['erreurs'])) {
                foreach ($resultats['erreurs'] as $err) {
                    $this->warn("    ⚠ {$err['eleve']} : {$err['erreur']}");
                }
            }
        });

        $this->info("Terminé : {$totalGenerees} facture(s) générée(s), {$totalIgnorees} ignorée(s).");
        return self::SUCCESS;
    }
}
```

---

## ÉTAPE 7 — Enregistrer la commande dans le scheduler

**Modifier :** `edugestdz/backend/routes/console.php`

Ajouter :

```php
use Illuminate\Support\Facades\Schedule;

// Génération automatique des factures le 1er de chaque mois à 6h00 (heure Alger)
Schedule::command('factures:generer-mensuel')
    ->monthlyOn(1, '06:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping()
    ->runInBackground();
```

---

## ÉTAPE 8 — Migration : ajouter type_ligne 'transport' et 'cantine'

**Créer :** `edugestdz/backend/database/migrations/2026_06_30_300000_update_lignes_facture_type_ligne.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL : modifier le type ENUM via contrainte CHECK
        // Supprimer l'ancienne contrainte et en créer une nouvelle
        DB::statement("ALTER TABLE lignes_facture DROP CONSTRAINT IF EXISTS lignes_facture_type_ligne_check");
        DB::statement("ALTER TABLE lignes_facture ADD CONSTRAINT lignes_facture_type_ligne_check
            CHECK (type_ligne IN ('cours', 'transport', 'cantine', 'inscription', 'autre'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lignes_facture DROP CONSTRAINT IF EXISTS lignes_facture_type_ligne_check");
        DB::statement("ALTER TABLE lignes_facture ADD CONSTRAINT lignes_facture_type_ligne_check
            CHECK (type_ligne IN ('cours', 'inscription', 'autre'))");
    }
};
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/FacturationIntegrationTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\Facture;
use App\Models\InscriptionCantine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TransportEleve;
use App\Models\ArretBus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturationIntegrationTest extends TestCase
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

    // ─── Facture scolarité seule ──────────────────────

    public function test_generer_facture_scolarite_seule(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['numero_facture', 'lignes', 'total_ttc']]);

        $this->assertDatabaseHas('factures', [
            'eleve_id' => $eleve->id,
            'mois'     => now()->month,
            'annee'    => now()->year,
            'total_ttc'=> 5000,
        ]);

        $this->assertDatabaseHas('lignes_facture', [
            'eleve_id'   => $eleve->id,
            'type_ligne' => 'cours',
            'total'      => 5000,
        ]);
    }

    // ─── Facture scolarité + transport ───────────────

    public function test_facture_inclut_transport_si_inscrit(): void
    {
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'tarif_mensuel' => 3500,
            'capacite'      => 20,
        ]);
        $arret = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arrêt Test',
            'ordre'      => 1,
        ]);
        TransportEleve::create([
            'tenant_id'              => $this->tenant->id,
            'eleve_id'               => $eleve->id,
            'circuit_id'             => $circuit->id,
            'arret_id'               => $arret->id,
            'abonnement'             => 'aller_retour',
            'date_debut'             => today()->startOfMonth(),
            'actif'                  => true,
            'tarif_mensuel_applique' => 3500,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Total = scolarité (5000) + transport (3500) = 8500
        $this->assertEquals(8500, $response->json('data.total_ttc'));

        $this->assertDatabaseHas('lignes_facture', [
            'type_ligne' => 'transport',
            'total'      => 3500,
        ]);
    }

    // ─── Facture scolarité + transport + cantine ─────

    public function test_facture_inclut_scolarite_transport_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        // Transport
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'tarif_mensuel' => 3000,
            'capacite'      => 20,
        ]);
        $arret = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arrêt', 'ordre' => 1,
        ]);
        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today()->startOfMonth(),
            'actif' => true, 'tarif_mensuel_applique' => 3000,
        ]);

        // Cantine forfait mensuel
        InscriptionCantine::create([
            'tenant_id'      => $this->tenant->id,
            'eleve_id'       => $eleve->id,
            'type_abonnement'=> 'mensuel',
            'regime'         => 'normal',
            'actif'          => true,
            'date_debut'     => today()->startOfMonth(),
            'tarif_mensuel'  => 2500,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Total = 5000 + 3000 + 2500 = 10500
        $this->assertEquals(10500, $response->json('data.total_ttc'));

        // Vérifier les 3 lignes
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'cours',     'total' => 5000]);
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'transport', 'total' => 3000]);
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'cantine',   'total' => 2500]);
    }

    // ─── Double facturation bloquée ──────────────────

    public function test_double_facturation_bloquee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $params = [
            'eleve_id'        => $eleve->id,
            'mois'            => now()->month,
            'annee'           => now()->year,
            'tarif_scolarite' => 5000,
        ];

        // Première génération → 201
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', $params)
            ->assertStatus(201);

        // Deuxième génération → 409 conflit
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', $params)
            ->assertStatus(409)
            ->assertJsonPath('code', 'DEJA_FACTUREE');
    }

    // ─── Génération toutes factures ──────────────────

    public function test_generer_toutes_dispatche_job(): void
    {
        \Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-toutes', [
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 4000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');

        \Queue::assertPushed(\App\Jobs\GenererFacturesMensuelles::class);
    }

    // ─── Isolation tenant ────────────────────────────

    public function test_facture_genere_uniquement_pour_tenant_courant(): void
    {
        $eleveA = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $autreTenant = Tenant::factory()->create();
        $eleveB      = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        // Générer pour eleve A
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleveA->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Tenter de générer pour eleve B (autre tenant) → 404
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleveB->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(422); // validation échoue car eleve_id n'existe pas dans ce tenant
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser develop avec main
git checkout develop
git pull origin main

# 1. Modifier FacturationService — ajouter 3 méthodes
modify: edugestdz/backend/app/Services/FacturationService.php

# 2. Modifier LigneFacture — ajouter accesseur type_label
modify: edugestdz/backend/app/Models/LigneFacture.php

# 3. Créer migration update type_ligne
create: edugestdz/backend/database/migrations/2026_06_30_300000_update_lignes_facture_type_ligne.php

# 4. Créer Job GenererFacturesMensuelles
create: edugestdz/backend/app/Jobs/GenererFacturesMensuelles.php

# 5. Créer Command Artisan
create: edugestdz/backend/app/Console/Commands/GenererFacturesMois.php

# 6. Modifier routes/api.php — ajouter 2 routes factures
modify: edugestdz/backend/routes/api.php

# 7. Modifier FactureController — ajouter genererMensuelle() et genererToutes()
modify: edugestdz/backend/app/Http/Controllers/Api/V1/FactureController.php

# 8. Modifier routes/console.php — scheduler mensuel
modify: edugestdz/backend/routes/console.php

# 9. Créer les tests
create: edugestdz/backend/tests/Feature/Api/FacturationIntegrationTest.php

# 10. Lancer la migration
php artisan migrate

# 11. Lancer les tests
php artisan test --parallel
# → Attendu : 275+ tests précédents + 7 nouveaux = 282+ tests verts

# 12. Si tout est vert
git add .
git commit -m "feat: Facturation intégrée — Transport + Cantine → Facture mensuelle élève + scheduler auto"
git push origin develop

# 13. Ouvrir PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_P2_FACTURATION_INTEGRATION.md
13 étapes dans l'ordre.

Objectif : Transport + Cantine intégrés automatiquement dans la facture mensuelle élève.
La facture est idempotente (pas de doublon si déjà générée pour ce mois).
Scheduler : génération automatique le 1er de chaque mois à 6h heure Alger.

php artisan test --parallel → 282+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
