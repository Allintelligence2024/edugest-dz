<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SuperAdminCompletTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'super_admin']);
        $superAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->token = JWTAuth::fromUser($superAdmin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_lister_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_stats_globales(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/stats')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_suspendre_tenant(): void
    {
        $target = Tenant::factory()->create(['statut' => 'actif']);

        $this->withToken($this->token)
            ->putJson("/api/v1/super-admin/tenants/{$target->id}", ['statut' => 'suspendu'])
            ->assertStatus(200);

        $this->assertEquals('suspendu', $target->fresh()->statut);
    }

    public function test_admin_normal_refuse(): void
    {
        $role = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
        ]);
        $tokenAdmin = JWTAuth::fromUser($admin);

        $this->withToken($tokenAdmin)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/super-admin/tenants')->assertStatus(401);
    }
}
