<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_config_fichier_existe(): void
    {
        $config = config('cors');

        $this->assertNotNull($config);
    }

    public function test_cors_allow_origins_inclut_localhost(): void
    {
        $origins = config('cors.allowed_origins', []);

        $this->assertContains('http://localhost:5173', $origins);
    }

    public function test_cors_patterns_inclut_vercel(): void
    {
        $patterns = config('cors.allowed_origins_patterns', []);

        $this->assertNotEmpty($patterns);

        $hasVercel = collect($patterns)->contains(fn($p) => str_contains($p, 'vercel'));
        $this->assertTrue($hasVercel, 'Pattern Vercel manquant dans CORS');
    }

    public function test_cors_patterns_inclut_railway(): void
    {
        $patterns = config('cors.allowed_origins_patterns', []);

        $hasRailway = collect($patterns)->contains(fn($p) => str_contains($p, 'railway'));
        $this->assertTrue($hasRailway, 'Pattern Railway manquant dans CORS');
    }

    public function test_cors_methods_inclut_wildcard(): void
    {
        $methods = config('cors.allowed_methods', []);

        $this->assertContains('*', $methods);
    }

    public function test_cors_headers_inclut_wildcard(): void
    {
        $headers = config('cors.allowed_headers', []);

        $this->assertContains('*', $headers);
    }

    public function test_route_api_accessible_sans_auth(): void
    {
        $response = $this->getJson('/api/v1/auth/login');

        $this->assertContains($response->status(), [422, 405]);
    }
}
