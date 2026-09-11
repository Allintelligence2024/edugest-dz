<?php

namespace Tests\Feature\Api;

use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\Facture;
use App\Models\InscriptionCantine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TransportEleve;
use App\Models\ArretBus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturationIntegrationTest extends TestCase
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

    // ─── Facture scolarité seule ──────────────────────

    public function test_generer_facture_scolarite_seule(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['numero_facture', 'lignes', 'total_ttc']]);

        $this->assertDatabaseHas('factures', [
            'eleve_id' => $eleve->id,
            'mois'     => now()->month,
            'annee'    => now()->year,
            'total_ttc'=> 5000,
        ]);

        $this->assertDatabaseHas('lignes_facture', [
            'facture_id' => Facture::where('eleve_id', $eleve->id)->first()->id,
            'type_ligne' => 'cours',
            'total'      => 5000,
        ]);
    }

    // ─── Facture scolarité + transport ───────────────

    public function test_facture_inclut_transport_si_inscrit(): void
    {
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'tarif_mensuel' => 3500,
            'capacite'      => 20,
        ]);
        $arret = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arrêt Test',
            'ordre'      => 1,
        ]);
        TransportEleve::create([
            'tenant_id'              => $this->tenant->id,
            'eleve_id'               => $eleve->id,
            'circuit_id'             => $circuit->id,
            'arret_id'               => $arret->id,
            'abonnement'             => 'aller_retour',
            'date_debut'             => today()->startOfMonth(),
            'actif'                  => true,
            'tarif_mensuel_applique' => 3500,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Total = scolarité (5000) + transport (3500) = 8500
        $this->assertEquals(8500, $response->json('data.total_ttc'));

        $this->assertDatabaseHas('lignes_facture', [
            'type_ligne' => 'transport',
            'total'      => 3500,
        ]);
    }

    // ─── Facture scolarité + transport + cantine ─────

    public function test_facture_inclut_scolarite_transport_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        // Transport
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'tarif_mensuel' => 3000,
            'capacite'      => 20,
        ]);
        $arret = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arrêt', 'ordre' => 1,
        ]);
        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today()->startOfMonth(),
            'actif' => true, 'tarif_mensuel_applique' => 3000,
        ]);

        // Cantine forfait mensuel
        InscriptionCantine::create([
            'tenant_id'      => $this->tenant->id,
            'eleve_id'       => $eleve->id,
            'type_abonnement'=> 'mensuel',
            'regime'         => 'normal',
            'actif'          => true,
            'date_debut'     => today()->startOfMonth(),
            'tarif_mensuel'  => 2500,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleve->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Total = 5000 + 3000 + 2500 = 10500
        $this->assertEquals(10500, $response->json('data.total_ttc'));

        // Vérifier les 3 lignes
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'cours',     'total' => 5000]);
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'transport', 'total' => 3000]);
        $this->assertDatabaseHas('lignes_facture', ['type_ligne' => 'cantine',   'total' => 2500]);
    }

    // ─── Double facturation bloquée ──────────────────

    public function test_double_facturation_bloquee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $params = [
            'eleve_id'        => $eleve->id,
            'mois'            => now()->month,
            'annee'           => now()->year,
            'tarif_scolarite' => 5000,
        ];

        // Première génération → 201
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', $params)
            ->assertStatus(201);

        // Deuxième génération → 409 conflit
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', $params)
            ->assertStatus(409)
            ->assertJsonPath('code', 'DEJA_FACTUREE');
    }

    // ─── Génération toutes factures ──────────────────

    public function test_generer_toutes_dispatche_job(): void
    {
        \Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-toutes', [
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 4000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');

        \Queue::assertPushed(\App\Jobs\GenererFacturesMensuelles::class);
    }

    // ─── Isolation tenant ────────────────────────────

    public function test_facture_genere_uniquement_pour_tenant_courant(): void
    {
        $eleveA = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $autreTenant = Tenant::factory()->create();
        $eleveB      = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        // Générer pour eleve A
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleveA->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(201);

        // Tenter de générer pour eleve B (autre tenant) → 404
        $this->withToken($this->token)
            ->postJson('/api/v1/factures/generer-mensuelle', [
                'eleve_id'        => $eleveB->id,
                'mois'            => now()->month,
                'annee'           => now()->year,
                'tarif_scolarite' => 5000,
            ])
            ->assertStatus(404); // validation/finding fails because eleve_id does not exist in this tenant
    }
}
