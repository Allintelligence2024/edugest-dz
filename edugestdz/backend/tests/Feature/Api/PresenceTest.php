<?php

namespace Tests\Feature\Api;

use App\Models\{Presence, Seance, Cours, Eleve, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PresenceTest extends TestCase
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

        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_saisir_presences(): void
    {
        $cours = Cours::factory()->create(['tenant_id' => $this->tenant->id]);
        $seance = Seance::factory()->create(['cours_id' => $cours->id, 'tenant_id' => $this->tenant->id, 'statut' => 'en_cours']);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/presences/seance/{$seance->id}", [
                'presences' => [
                    ['eleve_id' => $eleve->id, 'statut' => 'présent'],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_rapport_presence(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/presences/rapport')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $presence = Presence::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/presences/{$presence->id}", ['statut' => 'absent'])
            ->assertStatus(404);
    }
}
