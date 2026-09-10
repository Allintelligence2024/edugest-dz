<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsBasicTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
        ]);
        config(['tenant.current_id' => $this->tenant->id]);
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    }

    public function test_health_check(): void
    {
        $this->getJson('/api/v1/health')->assertStatus(200);
    }

    public function test_me_endpoint(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_eleves_liste_vide(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
    }

    public function test_enseignants_liste_vide(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/enseignants')
            ->assertStatus(200);
    }

    public function test_groupes_liste_vide(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/groupes')
            ->assertStatus(200);
    }

    public function test_404_route_inconnue(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/route-inexistante')
            ->assertStatus(404);
    }
}
