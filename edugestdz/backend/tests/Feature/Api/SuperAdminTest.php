<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, User, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'super_admin']);
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);
        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_tenants(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_creer_tenant(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/super-admin/tenants', [
                'nom_etablissement' => 'Nouveau Centre',
                'slug'              => 'nouveau-centre',
                'type_etablissement'=> 'centre_cours',
                'wilaya_id'         => 1,
                'email'             => 'centre@test.dz',
                'telephone'         => '0555123456',
                'plan_abonnement'   => 'gratuit',
                'admin_nom'         => 'Admin',
                'admin_prenom'      => 'Test',
                'admin_email'       => 'admin@test.dz',
                'admin_password'    => 'password123',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_stats(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/super-admin/stats')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_acces_non_super_admin_refuse(): void
    {
        $roleUser = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleUser->id,
        ]);
        $tokenUser = JWTAuth::fromUser($user);

        $this->withToken($tokenUser)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_suspendre_tenant(): void
    {
        $target = Tenant::factory()->create(['statut' => 'actif']);

        $this->withToken($this->token)
            ->putJson("/api/v1/super-admin/tenants/{$target->id}", ['statut' => 'suspendu'])
            ->assertStatus(200);

        $this->assertEquals('suspendu', $target->fresh()->statut);
    }
}
