<?php

namespace Tests\Feature\Api;

use App\Models\{Facture, LigneFacture, Eleve, PlanFractionnement, TrancheFractionnement, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PlanFractionnementTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_plan_fractionnement(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'total_ttc' => 100000,
            'statut' => 'émise',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/plans-fractionnement', [
                'facture_id' => $facture->id,
                'nb_tranches' => 4,
                'echeances' => [
                    now()->addMonth()->toDateString(),
                    now()->addMonths(2)->toDateString(),
                    now()->addMonths(3)->toDateString(),
                    now()->addMonths(4)->toDateString(),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nb_tranches' => 4,
                    'montant_total' => 100000,
                ],
            ]);

        $this->assertDatabaseHas('plans_fractionnement', [
            'facture_id' => $facture->id,
            'nb_tranches' => 4,
        ]);

        $this->assertCount(4, TrancheFractionnement::all());
    }

    public function test_double_affectation_rejetee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'total_ttc' => 50000,
        ]);

        $plan = PlanFractionnement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'facture_id' => $facture->id,
            'eleve_id' => $eleve->id,
            'montant_total' => 50000,
        ]);

        // Double affectation → même facture même élève
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/plans-fractionnement/affecter', [
                'plan_id' => $plan->id,
                'eleve_id' => $eleve->id,
            ]);

        // Le plan est déjà lié à cette facture/élève → exception ou success:false
        $response->assertStatus(422);
    }

    public function test_annuler_plan(): void
    {
        $plan = PlanFractionnement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut' => 'actif',
        ]);
        TrancheFractionnement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'statut' => 'en_attente',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/plans-fractionnement/{$plan->id}/annuler");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('plans_fractionnement', [
            'id' => $plan->id,
            'statut' => 'annulé',
        ]);
    }

    public function test_liste_plans(): void
    {
        PlanFractionnement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/plans-fractionnement');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['data' => [['id', 'nb_tranches', 'montant_total']]],
            ]);
    }

    public function test_consulter_plan(): void
    {
        $plan = PlanFractionnement::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        TrancheFractionnement::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/plans-fractionnement/{$plan->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'nb_tranches', 'montant_total', 'tranches'],
            ]);
    }

    public function test_validation_champs_requis(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/plans-fractionnement', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['facture_id', 'nb_tranches']);
    }
}
