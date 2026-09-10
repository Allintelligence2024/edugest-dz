<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SuperAdminExtTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'super_admin']);
        $this->superAdmin = User::factory()->create([
            'role_id'               => $role->id,
            'two_factor_secret'     => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->token = JWTAuth::fromUser($this->superAdmin);
    }

    public function test_lister_tenants(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_stats_globales(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'total_tenants', 'total_eleves', 'ca_global',
            ]]);
    }

    public function test_admin_ne_peut_pas_acceder_super_admin(): void
    {
        $roleUser = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create(['role_id' => $roleUser->id]);
        $tokenUser = JWTAuth::fromUser($user);

        $this->withToken($tokenUser)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_export_ical_planning(): void
    {
        $tenant = Tenant::factory()->create();
        $roleEns = Role::factory()->create(['nom' => 'enseignant']);
        $enseignant = User::factory()->create([
            'role_id'   => $roleEns->id,
            'tenant_id' => $tenant->id,
        ]);
        $tokenEns = JWTAuth::fromUser($enseignant);

        $this->withToken($tokenEns)
            ->get('/api/v1/planning/ical')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }
}
