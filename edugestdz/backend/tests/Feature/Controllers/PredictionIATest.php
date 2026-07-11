<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Eleve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PredictionIATest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $enseignant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $roleAdmin = Role::factory()->create(['nom' => 'admin']);
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleAdmin->id,
        ]);

        $roleEns = Role::factory()->create(['nom' => 'enseignant']);
        $this->enseignant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleEns->id,
        ]);
    }

    public function test_classement_risque_accessible(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/ia/prediction/classement')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['predictions', 'stats'], 'meta']);
    }

    public function test_classement_risque_retourne_predictions(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/ia/prediction/classement')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_predire_eleve_retourne_prediction(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/ia/prediction/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'eleve', 'prediction', 'profil_apprentissage', 'historique',
                ],
            ]);
    }

    public function test_predire_eleve_prediction_structure(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/ia/prediction/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'prediction' => [
                        'probabilite', 'confiance', 'niveau_risque',
                        'facteurs_risque', 'recommandations', 'moteur',
                    ],
                ],
            ]);
    }

    public function test_predire_tous_retourne_liste(): void
    {
        Eleve::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/ia/prediction/tout')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['predictions', 'stats', 'total']]);
    }

    public function test_predire_tous_avec_0_eleves(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/ia/prediction/tout')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 0);
    }

    public function test_classement_filtrer_par_niveau(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/ia/prediction/classement?niveau=critique')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_recalculer_tous_via_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/ia/prediction/tout')
            ->assertStatus(200);

        Queue::assertNothingPushed();
    }

    public function test_enseignant_accede_classement(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->getJson('/api/v1/ia/prediction/classement')
            ->assertStatus(200);
    }

    public function test_predire_eleve_inexistant_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/ia/prediction/eleve/eleve-inexistant')
            ->assertStatus(404);
    }
}
