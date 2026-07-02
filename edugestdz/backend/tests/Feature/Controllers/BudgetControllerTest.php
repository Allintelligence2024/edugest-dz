<?php
namespace Tests\Feature\Controllers;

use App\Models\{Depense, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_dashboard_budget(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(200);
    }

    public function test_dashboard_avec_periode(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/dashboard?mois=6&annee=2026')
            ->assertStatus(200);
    }

    public function test_lister_depenses(): void
    {
        Depense::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/budget/depenses')
            ->assertStatus(200);
    }

    public function test_creer_depense(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/depenses', [
                'libelle' => 'Achat fournitures bureau',
                'montant' => 15000,
                'categorie' => 'fournitures_bureau',
                'mois' => 7,
                'annee' => 2026,
                'date_depense' => today()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_creer_depense_montant_negatif_echoue(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/budget/depenses', [
                'libelle' => 'Test',
                'montant' => -500,
                'categorie' => 'loyer',
                'mois' => 7,
                'annee' => 2026,
            ])
            ->assertStatus(422);
    }

    public function test_modifier_depense(): void
    {
        $depense = Depense::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/budget/depenses/{$depense->id}", [
                'montant' => 25000,
            ])
            ->assertStatus(200);
    }

    public function test_supprimer_depense(): void
    {
        $depense = Depense::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/budget/depenses/{$depense->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('depenses', ['id' => $depense->id]);
    }

    public function test_previsionnel_annuel(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/previsionnel?annee=2026')
            ->assertStatus(200);
    }

    public function test_bilan_mensuel(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/budget/bilan-mensuel?mois=7&annee=2026')
            ->assertStatus(200);
    }
}
