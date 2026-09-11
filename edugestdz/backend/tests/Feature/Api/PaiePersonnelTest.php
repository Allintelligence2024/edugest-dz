<?php

namespace Tests\Feature\Api;

use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaiePersonnelTest extends TestCase
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

    public function test_calculer_paie_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'salaire_base' => 35000,
            'type_contrat' => 'CDI',
            'statut'       => 'actif',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/personnel/paies/calculer', [
                'agent_id' => $agent->id,
                'mois'     => now()->month,
                'annee'    => now()->year,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['paie', 'detail' => ['salaire_base', 'cnas', 'irg', 'salaire_net']]]);
    }

    public function test_paie_cdi_inclut_irg_cnas(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'salaire_base' => 50000,
            'type_contrat' => 'CDI',
            'num_cnas'     => '123456789',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/personnel/paies/calculer', [
                'agent_id' => $agent->id,
                'mois'     => now()->month,
                'annee'    => now()->year,
            ])
            ->assertStatus(201);

        $this->assertEquals('4500.00', $response->json('data.detail.cnas'));
        $this->assertLessThan(50000, $response->json('data.detail.salaire_net'));
    }

    public function test_valider_paie(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);
        $paie  = PaiePersonnel::create([
            'tenant_id' => $this->tenant->id,
            'agent_id'  => $agent->id,
            'mois'      => now()->month,
            'annee'     => now()->year,
            'salaire_base' => 30000,
            'salaire_net'  => 27000,
            'statut'       => 'brouillon',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/paies/{$paie->id}/valider")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'valide');
    }

    public function test_isolation_tenant_paie_personnel(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreAgent  = PersonnelNonEnseignant::factory()->create(['tenant_id' => $autreTenant->id]);
        $autrePaie   = PaiePersonnel::create([
            'tenant_id' => $autreTenant->id, 'agent_id' => $autreAgent->id,
            'mois' => now()->month, 'annee' => now()->year,
            'salaire_base' => 30000, 'salaire_net' => 27000, 'statut' => 'brouillon',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/paies/{$autrePaie->id}/valider")
            ->assertStatus(404);
    }
}
