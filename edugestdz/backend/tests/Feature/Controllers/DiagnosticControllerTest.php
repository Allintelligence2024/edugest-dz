<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Eleve;
use App\Models\DiagnosticEleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiagnosticControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $role = Role::factory()->create(['nom' => 'admin']);
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);
    }

    public function test_dashboard_diagnostic(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'total_analyses', 'par_niveau', 'actions_requises', 'top_risque',
            ]]);
    }

    public function test_lister_diagnostics(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/eleves')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_filtrer_par_niveau(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/diagnostic/eleves?niveau=critique')
            ->assertStatus(200);
    }

    public function test_analyser_eleve_sans_notes_retourne_normal(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/diagnostic/eleves/{$eleve->id}/analyser")
            ->assertStatus(200)
            ->assertJsonPath('data.niveau_global', 'normal')
            ->assertJsonPath('data.score_risque', '0.00');
    }

    public function test_analyser_tous(): void
    {
        Eleve::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/analyser-tous')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total']]);
    }

    public function test_creer_plan_rattrapage(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/rattrapages', [
                'eleve_id'   => $eleve->id,
                'matiere'    => 'Mathématiques',
                'objectifs'  => 'Maîtriser les équations du 2ème degré',
                'programme'  => '3 séances de 2h par semaine',
                'date_debut' => now()->addDay()->format('Y-m-d'),
                'date_fin'   => now()->addMonth()->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_envoyer_convocation(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/diagnostic/convocations', [
                'eleve_id' => $eleve->id,
                'motif'    => 'niveau_critique',
                'message'  => 'Veuillez vous présenter à l\'établissement.',
                'canal'    => 'sms',
            ])
            ->assertStatus(201);
    }

    public function test_detail_eleve_avec_diagnostic(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        DiagnosticEleve::create([
            'tenant_id'       => $eleve->tenant_id,
            'eleve_id'        => $eleve->id,
            'niveau_global'   => 'normal',
            'score_risque'    => 25,
            'moyenne_generale' => 12.5,
            'derniere_analyse' => now(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/diagnostic/eleves/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'diagnostic', 'historique', 'rattrapages', 'recommandations',
            ]]);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/diagnostic/dashboard')->assertStatus(401);
    }
}
