<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Eleve;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('auth');
    }

    public function test_health_check_retourne_200(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'version', 'services' => ['postgresql', 'redis', 'storage']]);
    }

    public function test_health_check_postgresql_ok(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $this->assertEquals('ok', $response->json('services.postgresql.status'));
    }

    public function test_api_retourne_security_headers(): void
    {
        $this->getJson('/api/health')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    public function test_login_rate_limiter_bloque_apres_10_tentatives(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'fake@test.com',
                'password' => 'wrongpassword',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'fake@test.com',
            'password' => 'wrongpassword',
        ])->assertStatus(429);
    }

    public function test_api_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/eleves')->assertStatus(401);
        $this->getJson('/api/v1/budget/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/transport/circuits')->assertStatus(401);
    }

    public function test_parent_ne_peut_pas_acceder_super_admin(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role   = Role::factory()->create(['nom' => 'parent']);
        $parent = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->actingAs($parent, 'api')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_admin_ne_peut_pas_impersonate_tenant(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role   = Role::factory()->create(['nom' => 'admin']);
        $admin  = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $role->id,
        ]);
        $target = Tenant::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson("/api/v1/super-admin/tenants/{$target->id}/impersonate")
            ->assertStatus(403);
    }
}
