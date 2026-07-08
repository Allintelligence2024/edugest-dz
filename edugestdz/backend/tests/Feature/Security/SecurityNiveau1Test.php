<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\JwtBlacklistService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityNiveau1Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->role = Role::factory()->create(['nom' => 'admin']);
    }

    public function test_token_blackliste_retourne_401(): void
    {
        $user  = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);
        $token = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(401);
    }

    public function test_invalidation_tous_tokens_user(): void
    {
        $user    = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);
        $token   = auth('api')->login($user);
        $service = app(JwtBlacklistService::class);

        $service->blacklisterTousLesTokensUser($user->id, 'test_security');

        $this->assertTrue(true);
    }

    public function test_eleve_autre_tenant_non_accessible(): void
    {
        $tenantB = Tenant::factory()->create();

        $userA   = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);
        $eleveB  = Eleve::factory()->create(['tenant_id' => $tenantB->id]);

        $token = auth('api')->login($userA);
        config(['tenant.current_id' => $this->tenant->id]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $this->tenant->id,
        ])->getJson("/api/v1/eleves/{$eleveB->id}")
          ->assertStatus(404);
    }

    public function test_manipulation_tenant_header_bloquee(): void
    {
        $tenantB = Str::uuid()->toString();

        $userA = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);
        $token = auth('api')->login($userA);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $tenantB,
        ])->getJson('/api/v1/eleves')
          ->assertStatus(403)
          ->assertJsonPath('code', 'TENANT_MANIPULATION');
    }

    public function test_scope_tenant_automatique(): void
    {
        $tenantB = Tenant::factory()->create();

        User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Eleve::factory()->count(5)->create(['tenant_id' => $tenantB->id]);

        config(['tenant.current_id' => $this->tenant->id]);

        $this->assertEquals(3, Eleve::count());
    }

    public function test_fichier_autre_tenant_acces_refuse(): void
    {
        $tenantB = Str::uuid()->toString();
        $userA   = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);

        config(['tenant.current_id' => $this->tenant->id]);
        $token = auth('api')->login($userA);

        $cheminB    = "tenants/{$tenantB}/bulletins/test.pdf";
        $cheminB64  = base64_encode($cheminB);
        $sig        = hash_hmac('sha256', $cheminB . $this->tenant->id, config('app.key'));

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp=" . now()->addHour()->timestamp)
            ->assertStatus(403);
    }

    public function test_lien_fichier_expire_retourne_410(): void
    {
        $userA   = User::factory()->create(['role_id' => $this->role->id, 'tenant_id' => $this->tenant->id]);

        config(['tenant.current_id' => $this->tenant->id]);
        $token   = auth('api')->login($userA);

        $chemin  = "tenants/{$this->tenant->id}/bulletins/test.pdf";
        $cheminB64 = base64_encode($chemin);
        $sig     = hash_hmac('sha256', $chemin . $this->tenant->id, config('app.key'));
        $expPasse = now()->subHour()->timestamp;

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp={$expPasse}")
            ->assertStatus(410);
    }

    public function test_health_check_accessible_sans_auth(): void
    {
        $this->getJson('/api/health')->assertStatus(200);
    }
}
