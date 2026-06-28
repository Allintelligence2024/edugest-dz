<?php

namespace Tests\Feature\Api;

use App\Models\{Matiere, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatiereTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_crud_matiere(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/matieres', [
                'nom_fr'       => 'Mathématiques',
                'nom_ar'       => 'الرياضيات',
                'coefficient'  => 3,
                'couleur'      => '#1E5EBC',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom_fr', 'Mathématiques');

        $matiere = Matiere::first();

        $this->withToken($this->token)
            ->putJson("/api/v1/matieres/{$matiere->id}", ['coefficient' => 5])
            ->assertStatus(200)
            ->assertJsonPath('data.coefficient', 5);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/matieres/{$matiere->id}")
            ->assertStatus(200);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $matiere = Matiere::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/matieres/{$matiere->id}")
            ->assertStatus(404);
    }
}
