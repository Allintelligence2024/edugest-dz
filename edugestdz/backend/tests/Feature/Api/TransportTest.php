<?php

namespace Tests\Feature\Api;

use App\Models\ArretBus;
use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TransportEleve;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_circuit(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/transport/circuits', [
                'nom'           => 'Circuit Nord Alger',
                'capacite'      => 25,
                'tarif_mensuel' => 3500,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Circuit Nord Alger');

        $this->assertDatabaseHas('circuits_transport', [
            'nom'       => 'Circuit Nord Alger',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_circuits_par_tenant(): void
    {
        CircuitTransport::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $autreTenant = Tenant::factory()->create();
        CircuitTransport::factory()->count(5)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transport/circuits')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 3);
    }

    public function test_afficher_circuit_avec_arrets(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['circuit', 'nb_eleves', 'taux_remplissage', 'places_restantes']]);
    }

    public function test_isolation_tenant_circuit(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreCircuit = CircuitTransport::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$autreCircuit->id}")
            ->assertStatus(404);
    }

    public function test_supprimer_circuit_vide(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('circuits_transport', ['id' => $circuit->id]);
    }

    public function test_supprimer_circuit_avec_eleves_bloque(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);
        $arret   = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arret Test',
            'ordre'      => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        TransportEleve::create([
            'tenant_id'              => $this->tenant->id,
            'eleve_id'               => $eleve->id,
            'circuit_id'             => $circuit->id,
            'arret_id'               => $arret->id,
            'abonnement'             => 'aller_retour',
            'date_debut'             => today()->toDateString(),
            'actif'                  => true,
            'tarif_mensuel_applique' => 3500,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_INSCRIPTIONS');
    }

    public function test_ajouter_arret(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/transport/circuits/{$circuit->id}/arrets", [
                'nom'         => 'Arret Pharmacie',
                'adresse'     => 'Rue Didouche Mourad',
                'ordre'       => 1,
                'heure_matin' => '07:15',
                'heure_soir'  => '17:30',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Arret Pharmacie');
    }

    public function test_inscrire_eleve_dans_circuit(): void
    {
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'capacite'      => 20,
            'tarif_mensuel' => 3500,
        ]);
        $arret = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arret Test',
            'ordre'      => 1,
        ]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id'   => $eleve->id,
                'circuit_id' => $circuit->id,
                'arret_id'   => $arret->id,
                'abonnement' => 'aller_retour',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.tarif_mensuel', '3500.00');

        $this->assertDatabaseHas('transport_eleves', [
            'eleve_id'   => $eleve->id,
            'circuit_id' => $circuit->id,
            'actif'      => true,
        ]);
    }

    public function test_double_inscription_bloquee(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id, 'capacite' => 20]);
        $arret   = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arret', 'ordre' => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today()->toDateString(),
            'actif' => true, 'tarif_mensuel_applique' => 3500,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id' => $eleve->id, 'circuit_id' => $circuit->id,
                'arret_id' => $arret->id, 'abonnement' => 'aller',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_INSCRIT');
    }

    public function test_circuit_complet_bloque(): void
    {
        $circuit = CircuitTransport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacite'  => 1,
        ]);
        $arret = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arret', 'ordre' => 1,
        ]);

        $eleve1 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve1->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today()->toDateString(),
            'actif' => true, 'tarif_mensuel_applique' => 3500,
        ]);

        $eleve2 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id' => $eleve2->id, 'circuit_id' => $circuit->id,
                'arret_id' => $arret->id, 'abonnement' => 'aller_retour',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CIRCUIT_COMPLET');
    }

    public function test_pointer_eleves_bus(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);
        $arret   = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arret', 'ordre' => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'trajet'     => 'matin',
                'date'       => today()->toDateString(),
                'pointages'  => [
                    ['eleve_id' => $eleve->id, 'arret_id' => $arret->id, 'statut' => 'monte'],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.enregistres', 1);

        $this->assertDatabaseHas('pointage_bus', [
            'eleve_id'   => $eleve->id,
            'circuit_id' => $circuit->id,
            'statut'     => 'monte',
            'trajet'     => 'matin',
        ]);
    }

    public function test_pointage_jour_circuit(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$circuit->id}/pointage?trajet=matin")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['circuit', 'date', 'trajet', 'liste', 'stats']]);
    }

    public function test_dashboard_transport(): void
    {
        CircuitTransport::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transport/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_circuits', 2)
            ->assertJsonStructure(['data' => ['nb_circuits', 'nb_eleves_total', 'alertes_maintenance', 'circuits']]);
    }
}
