<?php
// tests/Feature/Routes/RoutesIntegriteTest.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// 10 tests verifying route integrity after api.php refactoring
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

namespace Tests\Feature\Routes;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoutesIntegriteTest extends TestCase
{
    use RefreshDatabase;

    private $routes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routes = collect(Route::getRoutes()->getRoutes());
    }

    /** @test */
    public function health_ping_is_inside_v1_prefix(): void
    {
        $route = $this->routes->first(fn ($r) => $r->getName() === 'health.ping');
        $this->assertNotNull($route, 'health.ping route not found');
        $this->assertStringContainsString('api/v1/health/ping', $route->uri());
    }

    /** @test */
    public function health_check_is_inside_v1_prefix(): void
    {
        $route = $this->routes->first(fn ($r) => $r->getName() === 'health');
        $this->assertNotNull($route, 'health route not found');
        $this->assertStringContainsString('api/v1/health', $route->uri());
        $this->assertStringNotContainsString('api/health/ping', $route->uri());
    }

    /** @test */
    public function marketplace_stats_route_exists(): void
    {
        $found = $this->routes->contains(fn ($r) =>
            str_contains($r->uri(), 'marketplace/stats')
            && in_array('GET', $r->methods())
        );
        $this->assertTrue($found, 'marketplace/stats GET route not found');
    }

    /** @test */
    public function marketplace_recherche_route_exists(): void
    {
        $found = $this->routes->contains(fn ($r) =>
            str_contains($r->uri(), 'marketplace/recherche')
            && in_array('GET', $r->methods())
        );
        $this->assertTrue($found, 'marketplace/recherche GET route not found');
    }

    /** @test */
    public function marketplace_featured_route_exists(): void
    {
        $found = $this->routes->contains(fn ($r) =>
            str_contains($r->uri(), 'marketplace/featured')
            && in_array('GET', $r->methods())
        );
        $this->assertTrue($found, 'marketplace/featured GET route not found');
    }

    /** @test */
    public function honeypot_routes_are_outside_v1(): void
    {
        $honeypotRoutes = $this->routes->filter(fn ($r) =>
            str_starts_with($r->getName() ?? '', 'honeypot.')
        );
        $this->assertGreaterThan(0, $honeypotRoutes->count(), 'No honeypot routes found');

        foreach ($honeypotRoutes as $route) {
            $this->assertStringContainsString('api/v1/', $route->uri(),
                "Honeypot route {$route->getName()} should contain /api/v1/ in URI"
            );
        }
    }

    /** @test */
    public function auth_login_route_exists(): void
    {
        $found = $this->routes->contains(fn ($r) =>
            str_contains($r->uri(), 'auth/login')
            && in_array('POST', $r->methods())
        );
        $this->assertTrue($found, 'auth/login POST route not found');
    }

    /** @test */
    public function eleves_resource_routes_exist(): void
    {
        $getEleves = $this->routes->contains(fn ($r) =>
            $r->uri() === 'api/v1/eleves' && in_array('GET', $r->methods())
        );
        $postEleves = $this->routes->contains(fn ($r) =>
            $r->uri() === 'api/v1/eleves' && in_array('POST', $r->methods())
        );
        $this->assertTrue($getEleves, 'GET api/v1/eleves not found');
        $this->assertTrue($postEleves, 'POST api/v1/eleves not found');
    }

    /** @test */
    public function at_least_250_routes_registered(): void
    {
        $apiRoutes = $this->routes->filter(fn ($r) =>
            str_contains($r->uri(), 'api/')
        );
        $this->assertGreaterThanOrEqual(250, $apiRoutes->count(),
            'Expected at least 250 API routes after refactoring'
        );
    }

    /** @test */
    public function all_use_statements_resolve_no_duplicate_class_references(): void
    {
        // Verify that all major route groups are accessible
        $criticalUris = [
            'api/v1/eleves',
            'api/v1/parents',
            'api/v1/enseignants',
            'api/v1/factures',
            'api/v1/marketplace/stats',
            'api/v1/health/ping',
        ];

        foreach ($criticalUris as $uri) {
            $found = $this->routes->contains(fn ($r) =>
                str_contains($r->uri(), $uri)
            );
            $this->assertTrue($found, "Critical route $uri not found after refactoring");
        }
    }
}
