<?php
namespace Tests\Feature\Controllers;

use App\Models\{Eleve, MenuCantine, InscriptionCantine, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CantineControllerTest extends TestCase
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

    public function test_lister_menus_cantine(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/menus')
            ->assertStatus(200);
    }

    public function test_creer_menu_cantine(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/menus', [
                'date_repas' => now()->addDay()->format('Y-m-d'),
                'type_repas' => 'déjeuner',
                'entree' => 'Salade mechouia',
                'plat' => 'Couscous agneau',
                'dessert' => 'Fruit de saison',
                'prix' => 250,
            ])
            ->assertStatus(201);
    }

    public function test_creer_menu_sans_champs_requis(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/menus', [])
            ->assertStatus(422);
    }

    public function test_lister_inscriptions_cantine(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/inscriptions')
            ->assertStatus(200);
    }

    public function test_inscrire_eleve_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id' => $eleve->id,
                'type_repas' => 'déjeuner',
                'actif' => true,
            ])
            ->assertStatus(201);
    }

    public function test_pointer_repas_journalier(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/pointage', [
                'date_repas' => today()->format('Y-m-d'),
                'eleve_id' => $eleve->id,
                'present' => true,
                'type_repas' => 'déjeuner',
            ])
            ->assertStatus(201);
    }

    public function test_dashboard_cantine(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/dashboard')
            ->assertStatus(200);
    }
}
