<?php

namespace Tests\Feature\Api;

use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelTest extends TestCase
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

    public function test_creer_agent_valide(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/personnel', [
                'nom'           => 'BENALI',
                'prenom'        => 'Fatima',
                'poste'         => 'femme_menage',
                'type_contrat'  => 'CDI',
                'date_embauche' => '2024-01-15',
                'salaire_base'  => 30000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.poste', 'femme_menage');

        $this->assertDatabaseHas('personnel_non_enseignant', [
            'nom'       => 'BENALI',
            'prenom'    => 'Fatima',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_personnel_filtree_par_tenant(): void
    {
        PersonnelNonEnseignant::factory()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $autreTenant = Tenant::factory()->create();
        PersonnelNonEnseignant::factory()->count(3)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/personnel')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_afficher_fiche_agent(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['agent', 'poste_affiche', 'anciennete_ans', 'solde_conges'],
            ]);
    }

    public function test_modifier_agent(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/{$agent->id}", ['statut' => 'suspendu'])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'suspendu');
    }

    public function test_supprimer_agent_soft_delete(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('personnel_non_enseignant', ['id' => $agent->id]);
    }

    public function test_isolation_tenant_agent(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreAgent  = PersonnelNonEnseignant::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$autreAgent->id}")
            ->assertStatus(404);
    }

    public function test_lister_conges_agent(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}/conges")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['agent', 'conges', 'solde_restant', 'droit_annuel'],
            ]);
    }

    public function test_demander_conge_valide(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'date_debut' => today()->addDays(5)->toDateString(),
                'date_fin'   => today()->addDays(9)->toDateString(),
                'type'       => 'conge_annuel',
                'motif'      => 'Vacances',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['conge', 'nb_jours']]);
    }

    public function test_approuver_conge(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'date_debut' => today()->addDays(5)->toDateString(),
                'date_fin'   => today()->addDays(7)->toDateString(),
                'type'       => 'conge_annuel',
            ])
            ->assertStatus(201);

        $congeId = $response->json('data.conge.id');

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/conges/{$congeId}/statut", [
                'statut' => 'approuve',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.conge.statut', 'approuve');
    }

    public function test_arrivee_personnel_present(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '07:45',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'present');

        $this->assertDatabaseHas('pointage_personnel', [
            'agent_id' => $agent->id,
            'statut'   => 'present',
        ]);
    }

    public function test_arrivee_tardive_retard(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '09:15',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'retard');
    }

    public function test_double_arrivee_bloquee(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointagePersonnel::create([
            'tenant_id'     => $this->tenant->id,
            'agent_id'      => $agent->id,
            'date'          => today(),
            'heure_arrivee' => '08:00:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '08:05',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_POINTE');
    }

    public function test_depart_sans_arrivee_bloque(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/depart", [
                'heure_depart' => '17:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAS_ARRIVEE');
    }

    public function test_tableau_bord_jour(): void
    {
        PersonnelNonEnseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/personnel/tableau-bord')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['date', 'par_poste', 'stats']]);
    }

    public function test_historique_pointage_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointagePersonnel::create([
            'tenant_id'     => $this->tenant->id,
            'agent_id'      => $agent->id,
            'date'          => today(),
            'heure_arrivee' => '08:00:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}/pointer/historique")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['meta' => ['stats']]);
    }
}
