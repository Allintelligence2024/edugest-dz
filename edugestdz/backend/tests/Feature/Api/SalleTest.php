<?php

namespace Tests\Feature\Api;

use App\Models\{Salle, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalleTest extends TestCase
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

    public function test_crud_salle(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/salles', [
                'nom'        => 'Salle A101',
                'capacite'   => 20,
                'equipement' => ['tableau', 'projecteur'],
                'localisation' => 'RDC',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Salle A101');

        $salle = Salle::first();

        $this->withToken($this->token)
            ->getJson("/api/v1/salles/{$salle->id}")
            ->assertStatus(200);

        $this->withToken($this->token)
            ->putJson("/api/v1/salles/{$salle->id}", ['capacite' => 25])
            ->assertStatus(200)
            ->assertJsonPath('data.capacite', 25);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/salles/{$salle->id}")
            ->assertStatus(200);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $salle = Salle::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/salles/{$salle->id}")
            ->assertStatus(404);
    }
}
