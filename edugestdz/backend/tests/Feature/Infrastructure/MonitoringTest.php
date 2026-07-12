<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class MonitoringTest extends TestCase
{
    public function test_health_check_retourne_200_quand_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'environment',
                'response_time',
                'checks' => [
                    'database',
                    'redis',
                    'meilisearch',
                    'storage',
                    'audit_chain',
                    'kill_switch',
                ],
            ]);
    }

    public function test_health_check_database_est_postgresql(): void
    {
        $response = $this->getJson('/api/v1/health');
        $data     = $response->json();

        $this->assertEquals('ok', $data['checks']['database']['status'] ?? 'error');
        $this->assertEquals('postgresql', $data['checks']['database']['driver'] ?? '');
    }

    public function test_health_check_redis_est_ok(): void
    {
        $response = $this->getJson('/api/v1/health');
        $data     = $response->json();

        $this->assertEquals('ok', $data['checks']['redis']['status'] ?? 'error');
    }

    public function test_health_check_accessible_sans_authentification(): void
    {
        $this->getJson('/api/v1/health')->assertStatus(200);
    }

    public function test_health_check_contient_le_temps_de_reponse(): void
    {
        $response = $this->getJson('/api/v1/health');
        $data     = $response->json();

        $this->assertArrayHasKey('response_time', $data);
        $this->assertStringEndsWith('ms', $data['response_time']);
    }

    public function test_health_check_kill_switch_inactif_par_defaut(): void
    {
        \Cache::forget('kill_switch_active');

        $response = $this->getJson('/api/v1/health');
        $data     = $response->json();

        $this->assertEquals('inactive', $data['checks']['kill_switch']['status'] ?? 'ACTIVE');
    }

    public function test_sentry_config_presente(): void
    {
        $this->assertNotNull(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
        $this->assertArrayHasKey('environment', config('sentry'));
    }
}
