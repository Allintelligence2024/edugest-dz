<?php

namespace Tests\Feature\Infrastructure;

use App\Models\TenantModule;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class PointsFaiblesTest extends TestCase
{
    use RefreshDatabase;

    // ══ FIX 5 : PostgreSQL ════════════════════════════════════════════

    public function test_db_connection_est_pgsql(): void
    {
        $this->assertEquals('pgsql', \DB::connection()->getDriverName());
    }

    // ══ FIX 6 : Monitoring ════════════════════════════════════════════

    public function test_health_check_structure_complete(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status', 'timestamp', 'version', 'environment', 'response_time',
                'checks' => [
                    'database'   => ['status', 'driver'],
                    'redis'      => ['status'],
                    'meilisearch'=> ['status'],
                    'storage'    => ['status'],
                    'audit_chain'=> ['status'],
                    'kill_switch'=> ['status'],
                ],
            ]);
    }

    public function test_health_check_postgresql_detecte(): void
    {
        $data = $this->getJson('/api/v1/health')->json();
        $this->assertEquals('postgresql', $data['checks']['database']['driver']);
        $this->assertEquals('ok', $data['checks']['database']['status']);
    }

    public function test_health_check_redis_ok(): void
    {
        $data = $this->getJson('/api/v1/health')->json();
        $this->assertEquals('ok', $data['checks']['redis']['status']);
    }

    public function test_health_check_sans_auth(): void
    {
        $this->getJson('/api/v1/health')->assertStatus(200);
    }

    public function test_sentry_config_chargee(): void
    {
        $this->assertNotNull(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
    }

    // ══ FIX 7 : Marketplace ══════════════════════════════════════════

    public function test_marketplace_inactif_par_defaut_sans_record_bdd(): void
    {
        $tenantId = (string) Str::uuid();

        $actif = TenantModule::estActif($tenantId, 'marketplace');
        $this->assertFalse($actif, 'Marketplace doit être inactif par défaut sans configuration');
    }

    public function test_modules_core_actifs_par_defaut(): void
    {
        $tenantId = (string) Str::uuid();

        $this->assertTrue(TenantModule::estActif($tenantId, 'core'));
    }

    public function test_marketplace_activable_via_module_manager(): void
    {
        $tenantId = (string) Str::uuid();
        config(['tenant.current_id' => $tenantId]);

        TenantModule::activer($tenantId, 'marketplace');
        $this->assertTrue(TenantModule::estActif($tenantId, 'marketplace'));

        TenantModule::desactiver($tenantId, 'marketplace');
        $this->assertFalse(TenantModule::estActif($tenantId, 'marketplace'));
    }

    public function test_routes_marketplace_publiques_accessibles_sans_auth(): void
    {
        $this->getJson('/api/v1/marketplace/stats')->assertStatus(200);
        $this->getJson('/api/v1/marketplace/featured')->assertStatus(200);
        $this->getJson('/api/v1/marketplace/recherche')->assertStatus(200);
    }

    public function test_routes_marketplace_privees_requierent_auth(): void
    {
        $this->postJson('/api/v1/marketplace/reservations', [])->assertStatus(401);
        $this->postJson('/api/v1/marketplace/avis', [])->assertStatus(401);
    }

    public function test_profil_centre_sans_marketplace_actif_retourne_404(): void
    {
        $fakeTenantId = (string) Str::uuid();
        $this->getJson("/api/v1/marketplace/centres/{$fakeTenantId}")
            ->assertStatus(404);
    }

    public function test_surveillance_inactif_par_defaut(): void
    {
        $tenantId = (string) Str::uuid();
        $actif = TenantModule::estActif($tenantId, 'surveillance');
        $this->assertFalse($actif, 'Surveillance doit être inactive par défaut sans caméras physiques');
    }
}
