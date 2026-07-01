<?php

namespace Tests\Feature\Api;

use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntretienBatimentTest extends TestCase
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

    // ─── LOCAUX ──────────────────────────────────────

    public function test_creer_local(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/locaux', [
                'nom'          => 'Salle 101',
                'type'         => 'salle_cours',
                'etage'        => '1er étage',
                'superficie_m2'=> 45.5,
                'etat_general' => 'bon',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Salle 101')
            ->assertJsonPath('data.type_label', 'Salle de cours');

        $this->assertDatabaseHas('locaux_batiment', [
            'nom'       => 'Salle 101',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_locaux_par_tenant(): void
    {
        LocalBatiment::create(['tenant_id' => $this->tenant->id, 'nom' => 'Salle A', 'type' => 'salle_cours']);
        LocalBatiment::create(['tenant_id' => $this->tenant->id, 'nom' => 'Bureau', 'type' => 'bureau']);

        $autreTenant = Tenant::factory()->create();
        LocalBatiment::create(['tenant_id' => $autreTenant->id, 'nom' => 'Salle B', 'type' => 'salle_cours']);

        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/locaux')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 2);
    }

    // ─── PRESTATAIRES ────────────────────────────────

    public function test_creer_prestataire(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/prestataires', [
                'nom'        => 'Plomberie Alger',
                'specialite' => 'plomberie',
                'telephone'  => '0550123456',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Plomberie Alger');
    }

    // ─── INTERVENTIONS ───────────────────────────────

    public function test_signaler_intervention(): void
    {
        $local = LocalBatiment::create([
            'tenant_id' => $this->tenant->id, 'nom' => 'WC Nord', 'type' => 'sanitaires',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/interventions', [
                'titre'    => "Fuite d'eau robinet",
                'type'     => 'panne',
                'priorite' => 'haute',
                'local_id' => $local->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.intervention.statut', 'signale')
            ->assertJsonPath('data.priorite_label', 'Haute');

        $this->assertDatabaseHas('interventions_entretien', [
            'titre'     => "Fuite d'eau robinet",
            'tenant_id' => $this->tenant->id,
            'statut'    => 'signale',
        ]);
    }

    public function test_changer_statut_intervention(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'signale',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_resoudre_cree_depense_m13(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'en_cours',
            'titre'     => 'Réparation plomberie',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/resoudre", [
                'cout_reel'            => 25000,
                'rapport_intervention' => 'Robinet remplacé.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.depense_creee', true);

        $this->assertDatabaseHas('depenses', [
            'categorie' => 'maintenance_reparation',
            'montant'   => 25000,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertNotNull(
            InterventionEntretien::find($intervention->id)->depense_id
        );
    }

    public function test_resoudre_sans_cout_ne_cree_pas_depense(): void
    {
        $intervention = InterventionEntretien::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'en_cours',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/entretien/interventions/{$intervention->id}/resoudre", [
                'cout_reel' => 0,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.depense_creee', false);

        $this->assertDatabaseCount('depenses', 0);
    }

    public function test_isolation_tenant_intervention(): void
    {
        $autreTenant    = Tenant::factory()->create();
        $autreIntervention = InterventionEntretien::factory()->create([
            'tenant_id' => $autreTenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/entretien/interventions/{$autreIntervention->id}")
            ->assertStatus(404);
    }

    // ─── PRÉVENTIF ───────────────────────────────────

    public function test_planifier_entretien_preventif(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/entretien/preventif', [
                'nom'                => 'Nettoyage climatisation',
                'frequence'          => 'semestriel',
                'prochaine_echeance' => now()->addMonths(3)->toDateString(),
                'cout_estime'        => 8000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.frequence', 'semestriel');
    }

    public function test_realiser_preventif_met_a_jour_echeance(): void
    {
        $entretien = EntretienPreventif::create([
            'tenant_id'          => $this->tenant->id,
            'nom'                => 'Contrôle extincteurs',
            'frequence'          => 'annuel',
            'prochaine_echeance' => today()->toDateString(),
            'actif'              => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/entretien/preventif/{$entretien->id}/realiser", [
                'cout_reel' => 5000,
            ])
            ->assertStatus(200);

        $this->assertStringContainsString(
            (string) now()->addYear()->year,
            $response->json('data.prochaine_echeance')
        );
    }

    // ─── DASHBOARD ───────────────────────────────────

    public function test_dashboard_entretien(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/entretien/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['stats' => [
                    'tickets_ouverts', 'tickets_urgents',
                    'resolus_ce_mois', 'cout_mois',
                    'locaux_critique', 'preventifs_retard',
                ]],
            ]);
    }
}
