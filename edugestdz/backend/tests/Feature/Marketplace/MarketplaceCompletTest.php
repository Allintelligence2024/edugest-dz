<?php

namespace Tests\Feature\Marketplace;

use App\Models\{Tenant, User, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceCompletTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;
    private string $userId;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create([
            'nom_etablissement' => 'Centre Complet',
            'statut'            => 'actif',
        ]);
        $this->tenantId = $tenant->id;

        DB::table('tenant_modules')->insert([
            'id'         => Str::uuid()->toString(),
            'tenant_id'  => $this->tenantId,
            'module_key' => 'marketplace',
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role_id'   => $role->id,
        ]);
        $this->userId = $user->id;

        config(['tenant.current_id' => $this->tenantId]);
        $this->token = auth('api')->login($user);
    }

    public function test_recherche_offres_publiques(): void
    {
        $response = $this->getJson('/api/v1/marketplace/recherche');
        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_stats_marketplace(): void
    {
        $response = $this->getJson('/api/v1/marketplace/stats');
        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_featured_marketplace(): void
    {
        $response = $this->getJson('/api/v1/marketplace/featured');
        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_profil_centre(): void
    {
        $response = $this->getJson("/api/v1/marketplace/centres/{$this->tenantId}");
        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_health_ping_sans_auth(): void
    {
        $response = $this->getJson('/api/health/ping');
        $response->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_health_sans_auth(): void
    {
        $response = $this->getJson('/api/health');
        $this->assertContains($response->status(), [200, 503]);
    }

    public function test_mes_reservations_avec_auth(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/marketplace/mes-reservations');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_health_ping_reponse_rapide(): void
    {
        $start = microtime(true);
        $this->getJson('/api/health/ping');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $elapsed, 'Health ping should respond in < 500ms');
    }
}
