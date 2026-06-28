<?php

namespace Tests\Feature\Api;

use App\Models\{Campagne, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampagneTest extends TestCase
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

    public function test_creer_campagne(): void
    {
        Queue::fake();

        User::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'role_id' => Role::factory()->create()->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/campagnes', [
                'titre'   => 'Test campagne',
                'message' => 'Message de test',
                'canaux'  => ['in_app', 'email'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_liste_campagnes(): void
    {
        Campagne::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/campagnes')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_campagne_programmee(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/campagnes', [
                'titre'   => 'Programmée',
                'message' => 'Test',
                'canaux'  => ['in_app'],
                'programmee_le' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('campagnes', ['statut' => 'programmée']);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $campagne = Campagne::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/campagnes/{$campagne->id}")
            ->assertStatus(404);
    }
}
