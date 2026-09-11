<?php

namespace Tests\Feature\Api;

use App\Models\BudgetPrevisionnel;
use App\Models\Depense;
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

    public function test_categories_retourne_liste(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/categories')
            ->assertStatus(200)
            ->assertJsonCount(15, 'data');
    }

    public function test_creer_depense_valide(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/depenses', [
                'categorie'     => 'loyer',
                'libelle'       => 'Loyer juin 2026',
                'montant'       => 80000,
                'date_depense'  => '2026-06-01',
                'fournisseur'   => 'Proprietaire',
                'mode_paiement' => 'virement',
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

    public function test_definir_budget_previsionnel(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/previsionnel', [
                'annee' => 2026,
                'mois'  => 7,
                'lignes'=> [
                    ['categorie' => 'loyer',               'montant_prevu' => 80000],
                    ['categorie' => 'salaires_enseignants', 'montant_prevu' => 500000],
                    ['categorie' => 'electricite_gaz',     'montant_prevu' => 15000],
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
        BudgetPrevisionnel::create([
            'tenant_id'     => $this->tenant->id,
            'annee'         => now()->year,
            'mois'          => now()->month,
            'categorie'     => 'loyer',
            'montant_prevu' => 80000,
        ]);

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
        $this->assertEquals(-50000, $response->json('data.resultat_net'));
    }

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
