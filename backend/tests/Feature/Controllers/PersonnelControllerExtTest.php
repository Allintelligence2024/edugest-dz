<?php
namespace Tests\Feature\Controllers;

use App\Models\{PersonnelNonEnseignant, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelControllerExtTest extends TestCase
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

    public function test_lister_personnel(): void
    {
        PersonnelNonEnseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/personnel')
            ->assertStatus(200);
    }

    public function test_creer_personnel(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/personnel', [
                'nom' => 'Mansouri',
                'prenom' => 'Rachid',
                'poste' => 'agent_securite',
                'telephone' => '0555123456',
                'date_embauche' => '2024-09-01',
                'salaire_base' => 32000,
                'type_contrat' => 'CDI',
            ])
            ->assertStatus(201);
    }

    public function test_afficher_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);
    }

    public function test_modifier_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/{$agent->id}", [
                'salaire_base' => 35000,
            ])
            ->assertStatus(200);
    }

    public function test_supprimer_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);
    }

    public function test_generer_paie_personnel(): void
    {
        // TODO: endpoint /api/v1/personnel/{id}/paie non implémenté
        $this->markTestSkipped('Route non implémentée');
    }

    public function test_paie_mois_invalide_echoue(): void
    {
        // TODO: endpoint /api/v1/personnel/{id}/paie non implémenté
        $this->markTestSkipped('Route non implémentée');
    }

    public function test_conges_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'type' => 'conge_annuel',
                'date_debut' => now()->addWeek()->format('Y-m-d'),
                'date_fin' => now()->addWeeks(2)->format('Y-m-d'),
                'motif' => 'Congé annuel 2026',
            ])
            ->assertStatus(201);
    }
}
