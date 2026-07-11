<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Queue};
use Tests\TestCase;

class RgpdControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        $role = Role::firstOrCreate(['nom' => 'admin']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->fromUser($user);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_export_tenant_lance_job(): void
    {
        Queue::fake();

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/rgpd/export-tenant')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Queue::assertPushed(\App\Jobs\ExportDonneesTenantJob::class);
    }

    public function test_export_eleve_retourne_json(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/rgpd/export-eleve/{$eleve->id}");

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_export_eleve_autre_tenant_retourne_404(): void
    {
        $autreTenant = Tenant::factory()->create();
        $eleve = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/rgpd/export-eleve/{$eleve->id}")
            ->assertStatus(404);
    }

    public function test_demande_suppression_valide(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/demande-suppression', ['confirme' => true])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_demande_suppression_sans_confirmation_rejete(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/demande-suppression', ['confirme' => false])
            ->assertStatus(422);
    }

    public function test_liste_demandes_retourne_200(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/rgpd/demandes')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
