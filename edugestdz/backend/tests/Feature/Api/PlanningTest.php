<?php

namespace Tests\Feature\Api;

use App\Models\{Cours, Groupe, Matiere, Enseignant, Salle, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningTest extends TestCase
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

    public function test_planning_index(): void
    {
        Cours::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);

        $this->withToken($this->token)
            ->getJson('/api/v1/planning')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_planning_conflits(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/planning/conflits')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_generer_planning(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/planning/generer', [
                'semaine_debut' => now()->startOfWeek()->toDateString(),
                'semaine_fin'   => now()->endOfWeek()->toDateString(),
            ])
            ->assertStatus(200);
    }
}
