<?php

namespace Tests\Feature\Api\Marketplace;

use App\Models\{OffrePublique, Reservation, Avis, Enseignant, Eleve, User, Tenant, Role, Matiere};
use App\Services\Marketplace\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;
    protected Enseignant $enseignant;
    protected Eleve $eleve;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif', 'plan_abonnement' => 'pro']);

        $roleEnseignant = Role::factory()->create(['nom' => 'enseignant']);
        $roleAdmin      = Role::factory()->create(['nom' => 'admin']);

        $userEnseignant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleEnseignant->id,
        ]);
        $userEleve = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleAdmin->id,
        ]);

        $this->enseignant = Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $userEnseignant->id,
        ]);

        $this->eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $userEleve->id,
        ]);

        $this->token = auth('api')->login($userEleve);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_recherche_publique(): void
    {
        OffrePublique::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/marketplace/offres');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_recherche_avec_filtres(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        OffrePublique::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'matiere_id' => $matiere->id,
            'wilaya_id'  => 16,
            'tarif_seance' => 2000,
        ]);

        $response = $this->getJson('/api/v1/marketplace/offres?matiere_id=' . $matiere->id . '&wilaya_id=16');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_creer_offre(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/marketplace/offres', [
                'type_offre'   => 'enseignant',
                'matiere_id'   => $matiere->id,
                'niveau'       => '1AM',
                'tarif_seance' => 1500,
                'type_cours'   => 'presentiel',
                'wilaya_id'    => 16,
                'capacite_max' => 5,
                'description'  => 'Cours particulier de maths',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_reservation_flow(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $offre = OffrePublique::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'enseignant_id'   => $this->enseignant->id,
            'matiere_id'      => $matiere->id,
            'type_cours'      => 'en_ligne',
            'tarif_seance'    => 2000,
            'places_restantes'=> 3,
            'statut'          => 'active',
        ]);

        // 1. Créer réservation
        $resResponse = $this->withToken($this->token)
            ->postJson('/api/v1/marketplace/reservations', [
                'offre_id'   => $offre->id,
                'date_debut' => now()->addDays(3)->format('Y-m-d'),
                'message'    => 'Bonjour, je souhaite réserver',
            ]);

        $resResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $reservationId = $resResponse->json('data.id');

        // 2. Payer
        $payResponse = $this->withToken($this->token)
            ->postJson("/api/v1/marketplace/reservations/{$reservationId}/payer", [
                'type_paiement' => 'cib',
            ]);

        $payResponse->assertStatus(200);

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservationId,
            'statut' => 'payee',
        ]);
    }

    public function test_calcul_commission(): void
    {
        $service = app(CommissionService::class);

        $tenantGratuit = Tenant::factory()->create(['plan_abonnement' => 'gratuit']);
        $tenantPro = Tenant::factory()->create(['plan_abonnement' => 'pro']);
        $tenantPremium = Tenant::factory()->create(['plan_abonnement' => 'premium']);

        $this->assertEquals(1000, $service->calculateCommission(10000, $tenantGratuit));  // 10%
        $this->assertEquals(700,  $service->calculateCommission(10000, $tenantPro));      // 7%
        $this->assertEquals(500,  $service->calculateCommission(10000, $tenantPremium));  // 5%
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();

        $offre = OffrePublique::factory()->create([
            'tenant_id' => $autreTenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/marketplace/offres/{$offre->id}")
            ->assertStatus(404);
    }

    public function test_avis(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $offre = OffrePublique::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'enseignant_id'   => $this->enseignant->id,
            'matiere_id'      => $matiere->id,
            'tarif_seance'    => 2000,
            'places_restantes'=> 1,
            'statut'          => 'active',
        ]);

        // Créer réservation terminée
        $reservation = Reservation::factory()->terminee()->create([
            'tenant_id'    => $this->tenant->id,
            'offre_id'     => $offre->id,
            'eleve_id'     => $this->eleve->id,
            'enseignant_id'=> $this->enseignant->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/marketplace/avis', [
                'reservation_id' => $reservation->id,
                'note'           => 5,
                'commentaire'    => 'Excellent cours !',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('avis', [
            'reservation_id' => $reservation->id,
            'note'           => 5,
        ]);
    }
}
