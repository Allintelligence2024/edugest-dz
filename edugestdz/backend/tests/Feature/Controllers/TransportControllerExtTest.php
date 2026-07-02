<?php
namespace Tests\Feature\Controllers;

use App\Models\{CircuitTransport, Eleve, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportControllerExtTest extends TestCase
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

    public function test_lister_circuits(): void
    {
        CircuitTransport::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transport/circuits')
            ->assertStatus(200);
    }

    public function test_creer_circuit(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/transport/circuits', [
                'nom' => 'Circuit Ouest',
                'capacite' => 25,
                'immatriculation' => 'DZ-123-AB',
                'chauffeur_nom' => 'Belkacem Ahmed',
                'chauffeur_tel' => '0555987654',
                'tarif_mensuel' => 2500,
            ])
            ->assertStatus(201);
    }

    public function test_inscrire_eleve_transport(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscriptions', [
                'eleve_id' => $eleve->id,
                'circuit_id' => $circuit->id,
                'arret_id' => null,
                'actif' => true,
            ])
            ->assertStatus(201);
    }

    public function test_pointer_eleve_bus(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'eleve_id' => $eleve->id,
                'trajet' => 'aller',
                'present' => true,
                'heure' => '07:45',
            ])
            ->assertStatus(201);
    }

    public function test_trajet_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'eleve_id' => $eleve->id,
                'trajet' => 'tournee',
                'present' => true,
            ])
            ->assertStatus(422);
    }

    public function test_dashboard_transport(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/transport/dashboard')
            ->assertStatus(200);
    }
}
