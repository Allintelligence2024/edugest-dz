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
        $this->withToken($this->token)
            ->getJson('/api/v1/pointage/enseignants')
            ->assertStatus(200);
    }

    public function test_lister_badges_rfid(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/pointage/badges')
            ->assertStatus(200);
    }

    public function test_attribuer_badge_rfid(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badges', [
                'user_id' => $this->tenant->id,
                'user_type' => 'enseignant',
                'numero_badge' => 'RFID-' . rand(10000, 99999),
                'actif' => true,
            ])
            ->assertStatus(201);
    }

    public function test_rapport_pointage_periode(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/pointage/rapport?mois=7&annee=2026')
            ->assertStatus(200);
    }
}
