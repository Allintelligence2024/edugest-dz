<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\SecurityMonitorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityNiveau2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Role $role;
    private SecurityMonitorService $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->role = Role::factory()->create(['nom' => 'admin']);
        $this->monitor = app(SecurityMonitorService::class);
    }

    public function test_brute_force_bloque_apres_10_tentatives(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->loginEchoue('test@test.com', '1.2.3.4');
        }

        $this->assertTrue($this->monitor->estEnBruteForce('test@test.com', '1.2.3.4'));
    }

    public function test_brute_force_retourne_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->loginEchoue('victim@test.com', '127.0.0.1');
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'victim@test.com',
            'password' => 'anypassword',
        ])->assertStatus(429)
          ->assertJsonPath('code', 'BRUTE_FORCE_BLOCKED');
    }

    public function test_ips_differentes_compteurs_independants(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->monitor->loginEchoue('user@test.com', '1.1.1.1');
        }
        $this->assertFalse($this->monitor->estEnBruteForce('user@test.com', '2.2.2.2'));
        $this->assertFalse($this->monitor->estEnBruteForce('user@test.com', '1.1.1.1'));
    }

    public function test_admin_sans_mfa_bloque_routes_sensibles(): void
    {
        $admin = User::factory()->create([
            'role_id'              => $this->role->id,
            'tenant_id'            => $this->tenant->id,
            'two_factor_secret'    => null,
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_admin_avec_mfa_accede_normalement(): void
    {
        $admin = User::factory()->create([
            'role_id'              => $this->role->id,
            'tenant_id'            => $this->tenant->id,
            'two_factor_secret'    => 'JBSWY3DPEHPK3PXP',
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
    }

    public function test_parent_sans_mfa_non_bloque(): void
    {
        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $parent = User::factory()->create([
            'role_id'   => $roleParent->id,
            'tenant_id' => $this->tenant->id,
            'two_factor_secret' => null,
        ]);
        $token  = auth('api')->login($parent);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
    }

    public function test_evenement_securite_enregistre_en_bdd(): void
    {
        $this->monitor->enregistrerEvenement('test_event', 'info', ['detail' => 'test']);

        $this->assertDatabaseHas('security_events', [
            'type'     => 'test_event',
            'severite' => 'info',
        ]);
    }

    public function test_dashboard_securite_accessible_admin(): void
    {
        $admin = User::factory()->create([
            'role_id'              => $this->role->id,
            'tenant_id'            => $this->tenant->id,
            'two_factor_secret'    => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/security/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success','data' => ['critiques_24h','admins_sans_mfa']]);
    }

    public function test_encrypted_cast_chiffre_et_dechiffre(): void
    {
        $valeur = 'SECRET_TOKEN_12345';
        $cast   = new \App\Casts\EncryptedString();

        $chiffre  = $cast->set(null, 'test', $valeur, []);
        $this->assertNotEquals($valeur, $chiffre);

        $dechiffre = $cast->get(null, 'test', $chiffre, []);
        $this->assertEquals($valeur, $dechiffre);
    }

    public function test_valeur_non_chiffree_retournee_brute(): void
    {
        $cast       = new \App\Casts\EncryptedString();
        $nonChiffre = $cast->get(null, 'test', 'valeur_en_clair', []);
        $this->assertEquals('valeur_en_clair', $nonChiffre);
    }

    public function test_dashboard_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/security/dashboard')->assertStatus(401);
    }
}
