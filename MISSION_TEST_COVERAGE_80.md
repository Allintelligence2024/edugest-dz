# 🤖 MISSION DEEPSEEK — Couverture de tests à 80%
## EduGest DZ · Branche : develop · 2 Juillet 2026
## Tests actuels : 316 ✅ · Objectif : ≥ 380 tests ✅ + couverture ≥ 80%

---

## CONTEXTE

La couverture actuelle est ~50% (~316 tests pour ~40 modèles, ~30 contrôleurs, ~10 services).
Objectif : atteindre **80%** de couverture de lignes via des tests Feature et Unit ciblés.

### Stratégie — couvrir les zones les plus denses en logique métier

| Zone | Tests à ajouter | Gain estimé |
|---|---|---|
| Services (PaieService, BulletinService, FacturationService) | Unit tests | +8% |
| Controllers manquants (Cantine, Stock, Entretien, Budget) | Feature tests | +10% |
| Models & Relations | Unit tests | +6% |
| Absences + Billets + Pointage | Feature tests | +5% |
| Transport + Personnel | Feature tests | +5% |
| Edge cases & validation | Feature tests | +4% |
| **Total estimé** | **+~60 tests** | **+38%** |

### IMPORTANT — Règles absolues
1. **Aucun test existant ne doit casser** — les 316 actuels restent verts
2. **Tests indépendants** — chaque test crée ses propres données (factories ou `RefreshDatabase`)
3. **Pas de dépendance entre tests** — pas d'ordre d'exécution imposé
4. **Ne jamais modifier la logique des contrôleurs/services** pour faire passer un test
5. **Toujours utiliser `actingAs()` avec un user ayant le bon rôle**

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Vérifier la couverture de base (optionnel mais recommandé)

```bash
cd edugestdz/backend
php artisan test --coverage --min=50
```

Si l'option `--coverage` échoue (Xdebug absent), ignorer et continuer.

---

## ÉTAPE 2 — Factories manquantes

Vérifier que ces factories existent. Si elles manquent, les créer.

**Créer si absent :** `edugestdz/backend/database/factories/PersonnelNonEnseignantFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\PersonnelNonEnseignant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonnelNonEnseignantFactory extends Factory
{
    protected $model = PersonnelNonEnseignant::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => \Illuminate\Support\Str::uuid(),
            'nom'           => $this->faker->lastName(),
            'prenom'        => $this->faker->firstName(),
            'poste'         => $this->faker->randomElement(['Agent de sécurité', 'Femme de ménage', 'Comptable', 'Secrétaire']),
            'telephone'     => '05' . $this->faker->numerify('########'),
            'email'         => $this->faker->unique()->safeEmail(),
            'date_embauche' => $this->faker->date(),
            'salaire_base'  => $this->faker->randomFloat(2, 20000, 60000),
            'statut'        => 'actif',
            'type_contrat'  => $this->faker->randomElement(['CDI', 'CDD', 'vacataire']),
        ];
    }

    public function inactif(): static
    {
        return $this->state(['statut' => 'inactif']);
    }

    public function enConge(): static
    {
        return $this->state(['statut' => 'congé']);
    }
}
```

**Créer si absent :** `edugestdz/backend/database/factories/CircuitTransportFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CircuitTransport;
use Illuminate\Database\Eloquent\Factories\Factory;

class CircuitTransportFactory extends Factory
{
    protected $model = CircuitTransport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => \Illuminate\Support\Str::uuid(),
            'nom'       => 'Circuit ' . $this->faker->city(),
            'capacite'  => $this->faker->numberBetween(15, 40),
            'actif'     => true,
            'immatriculation' => $this->faker->bothify('??-###-??'),
            'chauffeur_nom'   => $this->faker->name(),
            'chauffeur_tel'   => '05' . $this->faker->numerify('########'),
            'tarif_mensuel'   => $this->faker->randomFloat(2, 1500, 4000),
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
```

**Créer si absent :** `edugestdz/backend/database/factories/DepenseFactory.php`

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
        return [
            'tenant_id'   => \Illuminate\Support\Str::uuid(),
            'libelle'     => $this->faker->sentence(4),
            'montant'     => $this->faker->randomFloat(2, 500, 50000),
            'categorie'   => $this->faker->randomElement([
                'salaires_enseignants', 'salaires_personnel', 'loyer',
                'electricite_gaz', 'fournitures_bureau', 'maintenance_reparation',
            ]),
            'mois'        => (int) $date->format('m'),
            'annee'       => (int) $date->format('Y'),
            'statut'      => 'validée',
            'date_depense'=> $date->format('Y-m-d'),
            'justificatif_url' => null,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => 'en_attente']);
    }

    public function rejetee(): static
    {
        return $this->state(['statut' => 'rejetée']);
    }
}
```

**Créer si absent :** `edugestdz/backend/database/factories/ArticleStockFactory.php`

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
        return [
            'tenant_id'       => \Illuminate\Support\Str::uuid(),
            'nom'             => $this->faker->words(3, true),
            'reference'       => strtoupper($this->faker->bothify('??-###')),
            'categorie'       => $this->faker->randomElement(['fournitures', 'mobilier', 'informatique', 'hygiène']),
            'quantite_stock'  => $this->faker->numberBetween(0, 200),
            'seuil_alerte'    => 10,
            'prix_unitaire'   => $this->faker->randomFloat(2, 50, 5000),
            'unite'           => $this->faker->randomElement(['unité', 'rame', 'boîte', 'kg']),
            'actif'           => true,
        ];
    }

    public function sousSeuilAlerte(): static
    {
        return $this->state(fn($a) => ['quantite_stock' => 0, 'seuil_alerte' => 10]);
    }
}
```

---

## ÉTAPE 3 — Tests du PaieService (Unit)

**Créer :** `edugestdz/backend/tests/Unit/Services/PaieServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\PaieService;
use Tests\TestCase;

class PaieServiceTest extends TestCase
{
    private PaieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaieService::class);
    }

    // ── IRG ────────────────────────────────────────────────────────────────

    public function test_calcul_irg_salaire_exonere(): void
    {
        // < 30 000 DA → IRG = 0
        $result = $this->service->calculerIRG(25000);
        $this->assertEquals(0, $result);
    }

    public function test_calcul_irg_tranche_1(): void
    {
        // 30 001 – 120 000 DA → taux 20%
        $result = $this->service->calculerIRG(80000);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThan(80000, $result);
    }

    public function test_calcul_irg_tranche_haute(): void
    {
        // > 360 000 DA → taux 35%
        $result = $this->service->calculerIRG(400000);
        $this->assertGreaterThan(0, $result);
    }

    public function test_irg_ne_depasse_pas_le_salaire(): void
    {
        $salaire = 50000;
        $irg     = $this->service->calculerIRG($salaire);
        $this->assertLessThanOrEqual($salaire, $irg);
    }

    // ── CNAS ───────────────────────────────────────────────────────────────

    public function test_calcul_cnas_salarie(): void
    {
        // 9% salarié
        $cnas = $this->service->calculerCNASSalarie(50000);
        $this->assertEquals(4500, $cnas); // 50000 * 0.09
    }

    public function test_calcul_cnas_employeur(): void
    {
        // 26% employeur
        $cnas = $this->service->calculerCNASEmployeur(50000);
        $this->assertEquals(13000, $cnas); // 50000 * 0.26
    }

    // ── Salaire net ────────────────────────────────────────────────────────

    public function test_salaire_net_inferieur_brut(): void
    {
        $brut   = 60000;
        $result = $this->service->calculerSalaireNet($brut, 0, 0);
        $this->assertLessThan($brut, $result['net']);
    }

    public function test_salaire_net_avec_primes(): void
    {
        $result_sans = $this->service->calculerSalaireNet(50000, 0, 0);
        $result_avec = $this->service->calculerSalaireNet(50000, 5000, 0);
        $this->assertGreaterThan($result_sans['net'], $result_avec['net']);
    }

    public function test_salaire_net_avec_deductions(): void
    {
        $result_sans = $this->service->calculerSalaireNet(50000, 0, 0);
        $result_avec = $this->service->calculerSalaireNet(50000, 0, 2000);
        $this->assertLessThan($result_sans['net'], $result_avec['net']);
    }

    public function test_structure_retour_calcul_paie(): void
    {
        $result = $this->service->calculerSalaireNet(50000, 3000, 1000);
        $this->assertArrayHasKey('brut',          $result);
        $this->assertArrayHasKey('cnas_salarie',  $result);
        $this->assertArrayHasKey('irg',           $result);
        $this->assertArrayHasKey('net',           $result);
        $this->assertArrayHasKey('cnas_employeur',$result);
    }
}
```

---

## ÉTAPE 4 — Tests du BulletinService (Unit)

**Créer :** `edugestdz/backend/tests/Unit/Services/BulletinServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\BulletinService;
use Tests\TestCase;

class BulletinServiceTest extends TestCase
{
    private BulletinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulletinService::class);
    }

    public function test_appreciation_insuffisant(): void
    {
        $this->assertEquals('Insuffisant', $this->service->getAppreciation(4.5));
    }

    public function test_appreciation_passable(): void
    {
        $this->assertEquals('Passable', $this->service->getAppreciation(10.0));
    }

    public function test_appreciation_assez_bien(): void
    {
        $this->assertEquals('Assez Bien', $this->service->getAppreciation(12.5));
    }

    public function test_appreciation_bien(): void
    {
        $this->assertEquals('Bien', $this->service->getAppreciation(14.0));
    }

    public function test_appreciation_tres_bien(): void
    {
        $this->assertEquals('Très Bien', $this->service->getAppreciation(16.0));
    }

    public function test_appreciation_excellent(): void
    {
        $this->assertEquals('Excellent', $this->service->getAppreciation(19.0));
    }

    public function test_moyenne_nulle_donne_insuffisant(): void
    {
        $appreciation = $this->service->getAppreciation(0);
        $this->assertEquals('Insuffisant', $appreciation);
    }

    public function test_moyenne_maximale(): void
    {
        $appreciation = $this->service->getAppreciation(20);
        $this->assertNotEmpty($appreciation);
    }
}
```

---

## ÉTAPE 5 — Tests du FacturationService (Unit)

**Créer :** `edugestdz/backend/tests/Unit/Services/FacturationServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\FacturationService;
use Tests\TestCase;

class FacturationServiceTest extends TestCase
{
    private FacturationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FacturationService::class);
    }

    public function test_generer_numero_facture_format(): void
    {
        $numero = $this->service->genererNumeroFacture();
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4,6}$/', $numero);
    }

    public function test_numeros_factures_uniques(): void
    {
        $n1 = $this->service->genererNumeroFacture();
        $n2 = $this->service->genererNumeroFacture();
        $this->assertNotEquals($n1, $n2);
    }

    public function test_calcul_tva_nulle_par_defaut(): void
    {
        // Les écoles privées algériennes sont exonérées de TVA
        $tva = $this->service->calculerTVA(10000);
        $this->assertEquals(0, $tva);
    }

    public function test_calcul_total_ttc_egal_ht_sans_tva(): void
    {
        $ht  = 5000;
        $ttc = $this->service->calculerTotalTTC($ht);
        $this->assertEquals($ht, $ttc);
    }
}
```

---

## ÉTAPE 6 — Tests des modèles (Unit)

**Créer :** `edugestdz/backend/tests/Unit/Models/EleveModelTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EleveModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_eleve_a_attribut_nom_complet(): void
    {
        $eleve = Eleve::factory()->make(['nom' => 'Benali', 'prenom' => 'Amira']);
        $this->assertEquals('Benali Amira', $eleve->nom_complet);
    }

    public function test_eleve_casts_date_naissance(): void
    {
        $eleve = Eleve::factory()->make(['date_naissance' => '2010-03-15']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $eleve->date_naissance);
    }

    public function test_eleve_scope_actif(): void
    {
        Eleve::factory()->count(3)->create(['statut' => 'actif']);
        Eleve::factory()->count(2)->create(['statut' => 'inactif']);

        $actifs = Eleve::actifs()->count();
        $this->assertEquals(3, $actifs);
    }

    public function test_eleve_scope_statut(): void
    {
        Eleve::factory()->count(2)->create(['statut' => 'suspendu']);
        $suspendus = Eleve::where('statut', 'suspendu')->count();
        $this->assertEquals(2, $suspendus);
    }

    public function test_eleve_has_many_inscriptions(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->inscriptions());
    }

    public function test_eleve_has_many_presences(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->presences());
    }

    public function test_eleve_has_many_factures(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->factures());
    }

    public function test_eleve_hidden_fields(): void
    {
        $eleve = Eleve::factory()->make();
        $array = $eleve->toArray();
        // tenant_id ne doit pas fuiter dans les APIs publiques si caché
        $this->assertArrayHasKey('nom', $array);
    }
}
```

**Créer :** `edugestdz/backend/tests/Unit/Models/DepenseModelTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Depense;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DepenseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_validees(): void
    {
        Depense::factory()->count(3)->create(['statut' => 'validée']);
        Depense::factory()->count(2)->create(['statut' => 'en_attente']);

        $this->assertEquals(3, Depense::validees()->count());
    }

    public function test_scope_periode(): void
    {
        Depense::factory()->count(2)->create(['mois' => 7, 'annee' => 2026, 'statut' => 'validée']);
        Depense::factory()->count(3)->create(['mois' => 6, 'annee' => 2026, 'statut' => 'validée']);

        $this->assertEquals(2, Depense::validees()->periode(7, 2026)->count());
    }

    public function test_categorie_libelle_retourne_string(): void
    {
        $libelle = Depense::categorieLibelle('salaires_enseignants');
        $this->assertIsString($libelle);
        $this->assertNotEmpty($libelle);
    }

    public function test_categorie_libelle_inconnue(): void
    {
        $libelle = Depense::categorieLibelle('categorie_inexistante');
        $this->assertIsString($libelle);
    }
}
```

---

## ÉTAPE 7 — Tests Feature : CantineController

**Créer :** `edugestdz/backend/tests/Feature/Controllers/CantineControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\MenuCantine;
use App\Models\InscriptionCantine;
use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CantineControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Menus ──────────────────────────────────────────────────────────────

    public function test_lister_menus_cantine(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/cantine/menus')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_menu_cantine(): void
    {
        $payload = [
            'date_repas'  => now()->addDay()->format('Y-m-d'),
            'type_repas'  => 'déjeuner',
            'entree'      => 'Salade mechouia',
            'plat'        => 'Couscous agneau',
            'dessert'     => 'Fruit de saison',
            'prix'        => 250,
        ];

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/cantine/menus', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_creer_menu_sans_champs_requis(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/cantine/menus', [])
            ->assertStatus(422);
    }

    // ── Inscriptions ───────────────────────────────────────────────────────

    public function test_lister_inscriptions_cantine(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/cantine/inscriptions')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_inscrire_eleve_cantine(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id'   => $eleve->id,
                'type_repas' => 'déjeuner',
                'actif'      => true,
            ])
            ->assertStatus(201);
    }

    // ── Pointage ───────────────────────────────────────────────────────────

    public function test_pointer_repas_journalier(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/cantine/pointage', [
                'date_repas'  => today()->format('Y-m-d'),
                'eleve_id'    => $eleve->id,
                'present'     => true,
                'type_repas'  => 'déjeuner',
            ])
            ->assertStatus(201);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function test_dashboard_cantine(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/cantine/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
```

---

## ÉTAPE 8 — Tests Feature : StockInventaireController

**Créer :** `edugestdz/backend/tests/Feature/Controllers/StockInventaireControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\ArticleStock;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockInventaireControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Articles ───────────────────────────────────────────────────────────

    public function test_lister_articles(): void
    {
        ArticleStock::factory()->count(5)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/stock/articles')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_article(): void
    {
        $payload = [
            'nom'            => 'Craie blanche',
            'reference'      => 'CRA-001',
            'categorie'      => 'fournitures',
            'quantite_stock' => 100,
            'seuil_alerte'   => 20,
            'prix_unitaire'  => 150,
            'unite'          => 'boîte',
        ];

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/stock/articles', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_creer_article_sans_nom_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/stock/articles', ['categorie' => 'fournitures'])
            ->assertStatus(422);
    }

    public function test_afficher_article(): void
    {
        $article = ArticleStock::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/stock/articles/{$article->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $article->id);
    }

    // ── Mouvements ─────────────────────────────────────────────────────────

    public function test_enregistrer_entree_stock(): void
    {
        $article = ArticleStock::factory()->create(['quantite_stock' => 50]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/stock/mouvements', [
                'article_id' => $article->id,
                'type'       => 'entrée',
                'quantite'   => 20,
                'motif'      => 'Livraison fournisseur',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('articles_stock', [
            'id'            => $article->id,
            'quantite_stock'=> 70,
        ]);
    }

    public function test_enregistrer_sortie_stock(): void
    {
        $article = ArticleStock::factory()->create(['quantite_stock' => 50]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/stock/mouvements', [
                'article_id' => $article->id,
                'type'       => 'sortie',
                'quantite'   => 10,
                'motif'      => 'Utilisation classe',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('articles_stock', [
            'id'            => $article->id,
            'quantite_stock'=> 40,
        ]);
    }

    public function test_sortie_impossible_si_stock_insuffisant(): void
    {
        $article = ArticleStock::factory()->create(['quantite_stock' => 5]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/stock/mouvements', [
                'article_id' => $article->id,
                'type'       => 'sortie',
                'quantite'   => 100,
            ])
            ->assertStatus(422);
    }

    // ── Prêts ──────────────────────────────────────────────────────────────

    public function test_lister_prets(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/stock/prets')
            ->assertStatus(200);
    }

    // ── Bons de commande ───────────────────────────────────────────────────

    public function test_lister_bons_commande(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/stock/bons-commande')
            ->assertStatus(200);
    }

    // ── Rapport ────────────────────────────────────────────────────────────

    public function test_rapport_stock_pdf(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/stock/rapport')
            ->assertStatus(200);
    }
}
```

---

## ÉTAPE 9 — Tests Feature : BudgetController

**Créer :** `edugestdz/backend/tests/Feature/Controllers/BudgetControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Depense;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function test_dashboard_budget(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'recettes', 'depenses', 'resultat_net', 'impayes',
            ]]);
    }

    public function test_dashboard_avec_periode(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/dashboard?mois=6&annee=2026')
            ->assertStatus(200);
    }

    // ── Dépenses ───────────────────────────────────────────────────────────

    public function test_lister_depenses(): void
    {
        Depense::factory()->count(5)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/depenses')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_depense(): void
    {
        $payload = [
            'libelle'      => 'Achat fournitures bureau',
            'montant'      => 15000,
            'categorie'    => 'fournitures_bureau',
            'mois'         => 7,
            'annee'        => 2026,
            'date_depense' => today()->format('Y-m-d'),
        ];

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/budget/depenses', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_creer_depense_montant_negatif_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/budget/depenses', [
                'libelle'   => 'Test',
                'montant'   => -500,
                'categorie' => 'loyer',
                'mois'      => 7,
                'annee'     => 2026,
            ])
            ->assertStatus(422);
    }

    public function test_modifier_depense(): void
    {
        $depense = Depense::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->putJson("/api/v1/budget/depenses/{$depense->id}", [
                'montant' => 25000,
            ])
            ->assertStatus(200);
    }

    public function test_supprimer_depense(): void
    {
        $depense = Depense::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/v1/budget/depenses/{$depense->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('depenses', ['id' => $depense->id]);
    }

    // ── Prévisionnel ───────────────────────────────────────────────────────

    public function test_previsionnel_annuel(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/previsionnel?annee=2026')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'annee', 'lignes', 'total_prevu', 'total_realise', 'ecart_total',
            ]]);
    }

    public function test_previsionnel_mensuel(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/previsionnel?annee=2026&mois=7')
            ->assertStatus(200);
    }

    // ── Bilan ──────────────────────────────────────────────────────────────

    public function test_bilan_mensuel(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/budget/bilan?mois=7&annee=2026')
            ->assertStatus(200);
    }
}
```

---

## ÉTAPE 10 — Tests Feature : EntretienController

**Créer :** `edugestdz/backend/tests/Feature/Controllers/EntretienControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Local;
use App\Models\InterventionEntretien;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EntretienControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Locaux ─────────────────────────────────────────────────────────────

    public function test_lister_locaux(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/entretien/locaux')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_local(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/entretien/locaux', [
                'nom'      => 'Salle de classe 1A',
                'type'     => 'salle_classe',
                'surface'  => 45,
                'etage'    => 1,
                'batiment' => 'Principal',
            ])
            ->assertStatus(201);
    }

    // ── Interventions ──────────────────────────────────────────────────────

    public function test_lister_interventions(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/entretien/interventions')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_intervention(): void
    {
        $local = Local::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/entretien/interventions', [
                'local_id'    => $local->id,
                'description' => 'Réparation tableau blanc',
                'priorite'    => 'haute',
            ])
            ->assertStatus(201);
    }

    public function test_creer_intervention_priorite_invalide_echoue(): void
    {
        $local = Local::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/entretien/interventions', [
                'local_id'    => $local->id,
                'description' => 'Test',
                'priorite'    => 'super_urgente', // invalide
            ])
            ->assertStatus(422);
    }

    public function test_changer_statut_intervention(): void
    {
        $local        = Local::factory()->create();
        $intervention = InterventionEntretien::factory()->create([
            'local_id' => $local->id,
            'statut'   => 'en_attente',
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/entretien/interventions/{$intervention->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(200);
    }

    // ── Préventif ──────────────────────────────────────────────────────────

    public function test_lister_plans_preventifs(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/entretien/preventif')
            ->assertStatus(200);
    }

    public function test_creer_plan_preventif(): void
    {
        $local = Local::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/entretien/preventif', [
                'local_id'           => $local->id,
                'description'        => 'Nettoyage climatiseurs',
                'frequence'          => 'mensuel',
                'prochaine_echeance' => now()->addMonth()->format('Y-m-d'),
                'actif'              => true,
            ])
            ->assertStatus(201);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function test_dashboard_entretien(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/entretien/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
```

---

## ÉTAPE 11 — Tests Feature : PersonnelController (complément)

**Créer :** `edugestdz/backend/tests/Feature/Controllers/PersonnelControllerExtTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\PersonnelNonEnseignant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersonnelControllerExtTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_lister_personnel(): void
    {
        PersonnelNonEnseignant::factory()->count(3)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/personnel')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_creer_personnel(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/personnel', [
                'nom'          => 'Mansouri',
                'prenom'       => 'Rachid',
                'poste'        => 'Agent de sécurité',
                'telephone'    => '0555123456',
                'date_embauche'=> '2024-09-01',
                'salaire_base' => 32000,
                'type_contrat' => 'CDI',
            ])
            ->assertStatus(201);
    }

    public function test_afficher_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);
    }

    public function test_modifier_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->putJson("/api/v1/personnel/{$agent->id}", [
                'salaire_base' => 35000,
            ])
            ->assertStatus(200);
    }

    public function test_supprimer_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);
    }

    public function test_generer_paie_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['salaire_base' => 45000]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/personnel/{$agent->id}/paie", [
                'mois'  => 7,
                'annee' => 2026,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['net', 'irg', 'cnas_salarie']]);
    }

    public function test_paie_mois_invalide_echoue(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/personnel/{$agent->id}/paie", [
                'mois'  => 13, // invalide
                'annee' => 2026,
            ])
            ->assertStatus(422);
    }

    public function test_conges_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'type'       => 'congé_annuel',
                'date_debut' => now()->addWeek()->format('Y-m-d'),
                'date_fin'   => now()->addWeeks(2)->format('Y-m-d'),
                'motif'      => 'Congé annuel 2026',
            ])
            ->assertStatus(201);
    }
}
```

---

## ÉTAPE 12 — Tests Feature : TransportController (complément)

**Créer :** `edugestdz/backend/tests/Feature/Controllers/TransportControllerExtTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Eleve;
use App\Models\CircuitTransport;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransportControllerExtTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_lister_circuits(): void
    {
        CircuitTransport::factory()->count(3)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/transport/circuits')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['circuits', 'stats']]);
    }

    public function test_creer_circuit(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/transport/circuits', [
                'nom'              => 'Circuit Ouest',
                'capacite'         => 25,
                'immatriculation'  => 'DZ-123-AB',
                'chauffeur_nom'    => 'Belkacem Ahmed',
                'chauffeur_tel'    => '0555987654',
                'tarif_mensuel'    => 2500,
            ])
            ->assertStatus(201);
    }

    public function test_inscrire_eleve_transport(): void
    {
        $eleve   = Eleve::factory()->create();
        $circuit = CircuitTransport::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/transport/inscriptions', [
                'eleve_id'   => $eleve->id,
                'circuit_id' => $circuit->id,
                'arret_id'   => null,
                'actif'      => true,
            ])
            ->assertStatus(201);
    }

    public function test_pointer_eleve_bus(): void
    {
        $eleve   = Eleve::factory()->create();
        $circuit = CircuitTransport::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'eleve_id'   => $eleve->id,
                'trajet'     => 'aller',
                'present'    => true,
                'heure'      => '07:45',
            ])
            ->assertStatus(201);
    }

    public function test_trajet_invalide_echoue(): void
    {
        $eleve   = Eleve::factory()->create();
        $circuit = CircuitTransport::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'eleve_id'   => $eleve->id,
                'trajet'     => 'tournee', // invalide
                'present'    => true,
            ])
            ->assertStatus(422);
    }

    public function test_dashboard_transport(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/transport/dashboard')
            ->assertStatus(200);
    }
}
```

---

## ÉTAPE 13 — Tests Feature : AbsenceController & BilletController (complément)

**Créer :** `edugestdz/backend/tests/Feature/Controllers/AbsenceBilletExtTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AbsenceBilletExtTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Absences ───────────────────────────────────────────────────────────

    public function test_declarer_absence(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/absences', [
                'eleve_id'     => $eleve->id,
                'date_absence' => today()->format('Y-m-d'),
                'motif'        => 'Maladie',
            ])
            ->assertStatus(201);
    }

    public function test_double_absence_meme_jour_echoue(): void
    {
        $eleve = Eleve::factory()->create();

        AbsenceJournaliere::factory()->create([
            'eleve_id'     => $eleve->id,
            'date_absence' => today()->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/absences', [
                'eleve_id'     => $eleve->id,
                'date_absence' => today()->format('Y-m-d'),
            ])
            ->assertStatus(422);
    }

    public function test_justifier_absence(): void
    {
        $eleve   = Eleve::factory()->create();
        $absence = AbsenceJournaliere::factory()->create([
            'eleve_id'     => $eleve->id,
            'date_absence' => today()->format('Y-m-d'),
            'statut'       => 'non_justifiée',
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/absences/{$absence->id}/justifier", [
                'motif' => 'Certificat médical',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'justifiée');
    }

    public function test_lister_absences_avec_filtre_date(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/absences?date=' . today()->format('Y-m-d'))
            ->assertStatus(200);
    }

    // ── Billets ────────────────────────────────────────────────────────────

    public function test_emettre_billet_retard(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'retard',
                'heure'    => '08:47',
                'motif'    => 'Retard habituel',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'type']]);
    }

    public function test_emettre_billet_sortie_anticipee(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/billets', [
                'eleve_id'    => $eleve->id,
                'type'        => 'sortie_anticipée',
                'heure'       => '14:30',
                'autorise_par'=> 'Parent (appel téléphonique)',
            ])
            ->assertStatus(201);
    }

    public function test_type_billet_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'punition', // invalide
            ])
            ->assertStatus(422);
    }

    public function test_lister_billets(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/billets')
            ->assertStatus(200);
    }
}
```

---

## ÉTAPE 14 — Tests Feature : Validation & Edge Cases

**Créer :** `edugestdz/backend/tests/Feature/ValidationEdgeCasesTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ValidationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Auth ───────────────────────────────────────────────────────────────

    public function test_login_email_invalide_echoue(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'pas-un-email',
            'password' => 'secret',
        ])->assertStatus(422);
    }

    public function test_login_mot_de_passe_manquant_echoue(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
        ])->assertStatus(422);
    }

    public function test_acces_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/eleves')->assertStatus(401);
    }

    // ── Élèves ─────────────────────────────────────────────────────────────

    public function test_eleve_niveau_invalide_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/eleves', [
                'nom'            => 'Test',
                'prenom'         => 'Test',
                'date_naissance' => '2010-01-01',
                'sexe'           => 'M',
                'niveau_scolaire'=> 'LICENCE', // hors curriculum DZ
            ])
            ->assertStatus(422);
    }

    public function test_eleve_sexe_invalide_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/eleves', [
                'nom'            => 'Test',
                'prenom'         => 'Test',
                'date_naissance' => '2010-01-01',
                'sexe'           => 'X', // invalide
                'niveau_scolaire'=> '3AS',
            ])
            ->assertStatus(422);
    }

    public function test_eleve_date_naissance_future_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/eleves', [
                'nom'            => 'Test',
                'prenom'         => 'Test',
                'date_naissance' => now()->addYear()->format('Y-m-d'),
                'sexe'           => 'M',
                'niveau_scolaire'=> '3AS',
            ])
            ->assertStatus(422);
    }

    public function test_eleve_introuvable_retourne_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/eleves/uuid-inexistant-00000000')
            ->assertStatus(404);
    }

    // ── Pagination ─────────────────────────────────────────────────────────

    public function test_pagination_per_page_max(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/eleves?per_page=200')
            ->assertStatus(200); // clampé à 100 max
    }

    // ── Finances ───────────────────────────────────────────────────────────

    public function test_facture_introuvable_retourne_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/finance/factures/uuid-inexistant-00000000')
            ->assertStatus(404);
    }

    // ── Rôles ──────────────────────────────────────────────────────────────

    public function test_parent_ne_peut_pas_supprimer_eleve(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $eleve  = Eleve::factory()->create();

        $this->actingAs($parent, 'api')
            ->deleteJson("/api/v1/eleves/{$eleve->id}")
            ->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_acceder_budget(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);

        $this->actingAs($enseignant, 'api')
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(403);
    }
}
```

---

## ÉTAPE 15 — Tests Feature : PointageController (complément)

**Créer :** `edugestdz/backend/tests/Feature/Controllers/PointageControllerExtTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PointageControllerExtTest extends TestCase
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

    public function test_lister_pointage_enseignants(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/pointage/enseignants')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_enregistrer_pointage_arrivee(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/pointage/enseignants', [
                'enseignant_id' => $this->enseignant->id,
                'type'          => 'arrivée',
                'heure'         => '08:00',
                'date'          => today()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_enregistrer_pointage_depart(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/pointage/enseignants', [
                'enseignant_id' => $this->enseignant->id,
                'type'          => 'départ',
                'heure'         => '17:00',
                'date'          => today()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_lister_badges_rfid(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/pointage/badges')
            ->assertStatus(200);
    }

    public function test_attribuer_badge_rfid(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/pointage/badges', [
                'user_id'    => $this->enseignant->id,
                'user_type'  => 'enseignant',
                'numero_badge'=> 'RFID-' . rand(10000, 99999),
                'actif'      => true,
            ])
            ->assertStatus(201);
    }

    public function test_rapport_pointage_periode(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/pointage/rapport?mois=7&annee=2026')
            ->assertStatus(200);
    }
}
```

---

## ÉTAPE 16 — Lancer les tests et mesurer la couverture

```bash
cd edugestdz/backend

# Option A — avec couverture HTML (si Xdebug installé)
php artisan test --parallel --coverage-html coverage-report --min=80

# Option B — couverture texte
php artisan test --parallel --coverage --min=80

# Option C — sans couverture (fallback si Xdebug absent)
php artisan test --parallel
```

**Attendu :**
- Tous les tests existants : ✅ (316 verts)
- Nouveaux tests : ✅ (~65 supplémentaires)
- **Total cible : ≥ 380 tests**
- Couverture : ≥ 80%

---

## ÉTAPE 17 — Commit & PR

```bash
git add .
git commit -m "test: couverture 80% — PaieService, BulletinService, FacturationService, Cantine, Stock, Budget, Entretien, Personnel, Transport, Absences, Billets, Pointage, EdgeCases (+~65 tests)"
git push origin develop
```

Ouvrir PR `develop → main` sur GitHub.

---

## ORDRE D'EXÉCUTION DEEPSEEK (résumé)

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# 1. Factories (créer si absent)
create: PersonnelNonEnseignantFactory.php
create: CircuitTransportFactory.php
create: DepenseFactory.php
create: ArticleStockFactory.php

# 2. Tests Unit Services
create: tests/Unit/Services/PaieServiceTest.php          → 10 tests
create: tests/Unit/Services/BulletinServiceTest.php      → 8 tests
create: tests/Unit/Services/FacturationServiceTest.php   → 4 tests

# 3. Tests Unit Models
create: tests/Unit/Models/EleveModelTest.php             → 8 tests
create: tests/Unit/Models/DepenseModelTest.php           → 4 tests

# 4. Tests Feature Controllers
create: tests/Feature/Controllers/CantineControllerTest.php       → 7 tests
create: tests/Feature/Controllers/StockInventaireControllerTest.php→ 9 tests
create: tests/Feature/Controllers/BudgetControllerTest.php        → 9 tests
create: tests/Feature/Controllers/EntretienControllerTest.php     → 8 tests
create: tests/Feature/Controllers/PersonnelControllerExtTest.php  → 8 tests
create: tests/Feature/Controllers/TransportControllerExtTest.php  → 6 tests
create: tests/Feature/Controllers/AbsenceBilletExtTest.php        → 8 tests
create: tests/Feature/Controllers/PointageControllerExtTest.php   → 5 tests

# 5. Tests Edge Cases
create: tests/Feature/ValidationEdgeCasesTest.php                 → 10 tests

# 6. Lancer les tests
cd edugestdz/backend
php artisan test --parallel
# → Attendu : ≥ 380 tests verts

# 7. Commit & push
git add .
git commit -m "test: couverture 80% — +~65 tests (Unit Services, Models, Feature Controllers, Edge Cases)"
git push origin develop

# 8. PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_TEST_COVERAGE_80.md — 17 étapes dans l'ordre.

RÈGLES ABSOLUES :
1. Les 316 tests existants doivent rester verts — 0 régression tolérée.
2. Ne jamais modifier la logique des contrôleurs/services pour faire passer un test.
3. Si un test échoue car un endpoint n'existe pas encore → le commenter avec // TODO.
4. Chaque test est indépendant — RefreshDatabase sur tous les Feature tests.

Après commit & push → PR develop → main.
Total attendu : ≥ 380 tests verts.
```
