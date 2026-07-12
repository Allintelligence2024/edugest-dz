<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChargeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = \App\Models\Tenant::factory()->create([
            'nom_etablissement' => 'Centre Perf',
            'statut'            => 'actif',
        ]);
        config(['tenant.current_id' => $tenant->id]);
    }

    public function test_health_ping_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/health/ping');
        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(500, $elapsed, 'Health ping must respond in < 500ms');
    }

    public function test_marketplace_stats_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/marketplace/stats');
        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(2000, $elapsed, 'Marketplace stats must respond in < 2s');
    }

    public function test_marketplace_recherche_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/marketplace/recherche');
        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(2000, $elapsed, 'Marketplace recherche must respond in < 2s');
    }

    public function test_marketplace_featured_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/marketplace/featured');
        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(2000, $elapsed, 'Marketplace featured must respond in < 2s');
    }

    public function test_health_check_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/health');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertContains($response->status(), [200, 503]);
        $this->assertLessThan(3000, $elapsed, 'Health check must respond in < 3s');
    }

    public function test_requete_concurrente_marketplace(): void
    {
        $startTime = microtime(true);
        $responses = [];

        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/v1/marketplace/stats');
        }

        $elapsed = (microtime(true) - $startTime) * 1000;

        foreach ($responses as $response) {
            $response->assertOk();
        }

        $avgLatency = $elapsed / 10;
        $this->assertLessThan(2000, $avgLatency, 'Average latency per request must be < 2s');
    }

    public function test_dashboard_analytics_reponse_rapide(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/analytics/dashboard');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertContains($response->status(), [200, 401, 404]);
        $this->assertLessThan(3000, $elapsed, 'Analytics dashboard must respond in < 3s');
    }
}
