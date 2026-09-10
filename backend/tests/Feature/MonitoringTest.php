<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_200_or_503(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
    }

    public function test_health_has_checks_array(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertJsonStructure(['status', 'checks']);
    }

    public function test_postgresql_check_retourne_ok(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_redis_check_present(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertJsonStructure(['checks' => ['redis' => ['status']]]);
    }

    public function test_storage_check_present(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertJsonStructure(['checks' => ['storage' => ['status']]]);
    }

    public function test_health_has_version_and_environment(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertJsonStructure(['status', 'version', 'environment']);
    }

    public function test_ping_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/health/ping');
        $response->assertStatus(200);
    }

    public function test_ping_has_timestamp(): void
    {
        $response = $this->getJson('/api/v1/health/ping');
        $response->assertJsonStructure(['status', 'timestamp']);
    }
}
