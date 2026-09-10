<?php
namespace Tests\Feature\Controllers;

use App\Models\{EntretienPreventif, InterventionEntretien, LocalBatiment, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntretienControllerTest extends TestCase
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

    public function test_lister_locaux(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/locaux')
            ->assertStatus(200);
    }

    public function test_creer_local(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/locaux', [
                'nom' => 'Salle de classe 1A',
                'type' => 'salle_cours',
                'superficie_m2' => 45,
                'etage' => '1',
                'etat_general' => 'bon',
            ])
            ->assertStatus(201);
    }

    public function test_lister_interventions(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/interventions')
            ->assertStatus(200);
    }

    public function test_creer_intervention(): void
    {
        $local = new LocalBatiment();
        $local->tenant_id = $this->tenant->id;
        $local->nom = 'Salle 1A';
        $local->type = 'salle_cours';
        $local->etat_general = 'bon';
        $local->actif = true;
        $local->save();

        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/interventions', [
                'local_id' => $local->id,
                'titre' => 'Réparation tableau blanc',
                'type' => 'panne',
                'description' => 'Réparation tableau blanc',
                'priorite' => 'haute',
            ])
            ->assertStatus(201);
    }

    public function test_creer_intervention_priorite_invalide_echoue(): void
    {
        $local = new LocalBatiment();
        $local->tenant_id = $this->tenant->id;
        $local->nom = 'Salle 1B';
        $local->type = 'salle_cours';
        $local->etat_general = 'bon';
        $local->actif = true;
        $local->save();

        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/interventions', [
                'local_id' => $local->id,
                'titre' => 'Test',
                'type' => 'panne',
                'description' => 'Test',
                'priorite' => 'super_urgente',
            ])
            ->assertStatus(422);
    }

    public function test_changer_statut_intervention(): void
    {
        $local = new LocalBatiment();
        $local->tenant_id = $this->tenant->id;
        $local->nom = 'Salle 1C';
        $local->type = 'salle_cours';
        $local->etat_general = 'bon';
        $local->actif = true;
        $local->save();

        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'local_id' => $local->id,
            'statut' => 'en_attente',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(200);
    }

    public function test_lister_plans_preventifs(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/preventif')
            ->assertStatus(200);
    }

    public function test_creer_plan_preventif(): void
    {
        $local = new LocalBatiment();
        $local->tenant_id = $this->tenant->id;
        $local->nom = 'Salle 1D';
        $local->type = 'salle_cours';
        $local->etat_general = 'bon';
        $local->actif = true;
        $local->save();

        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/preventif', [
                'local_id' => $local->id,
                'nom' => 'Nettoyage climatiseurs',
                'description' => 'Nettoyage climatiseurs Salle 1D',
                'frequence' => 'mensuel',
                'prochaine_echeance' => now()->addMonth()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_dashboard_entretien(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/dashboard')
            ->assertStatus(200);
    }
}
