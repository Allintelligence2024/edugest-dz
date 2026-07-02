<?php
namespace Tests\Feature\Controllers;

use App\Models\{Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointageControllerExtTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_lister_pointage_enseignants(): void
    {
        // TODO: endpoint /api/v1/pointage/enseignants non implémenté
        $this->markTestSkipped('Route non implémentée');
    }

    public function test_lister_badges_rfid(): void
    {
        // TODO: endpoint /api/v1/pointage/badges non implémenté
        $this->markTestSkipped('Route non implémentée');
    }

    public function test_attribuer_badge_rfid(): void
    {
        // TODO: endpoint POST /api/v1/pointage/badges non implémenté
        $this->markTestSkipped('Route non implémentée');
    }

    public function test_rapport_pointage_periode(): void
    {
        // TODO: endpoint /api/v1/pointage/rapport non implémenté
        $this->markTestSkipped('Route non implémentée');
    }
}
