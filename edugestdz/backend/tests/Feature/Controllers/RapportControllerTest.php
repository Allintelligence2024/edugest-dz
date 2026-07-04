<?php

namespace Tests\Feature\Controllers;

use App\Models\{AbsenceJournaliere, Eleve, Evaluation, Groupe, Inscription, Matiere, Note, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapportControllerTest extends TestCase
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

    public function test_peut_generer_rapport_absences_pdf(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        AbsenceJournaliere::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
        ]);

        $this->withToken($this->token)
            ->get('/api/v1/rapports/absences-pdf?mois=' . now()->month . '&annee=' . now()->year)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_peut_consulter_statistiques_absences(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        AbsenceJournaliere::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'statut' => 'non_justifiée',
        ]);
        AbsenceJournaliere::factory()->count(2)->justifiee()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/rapports/absences-stats?eleve_id=' . $eleve->id)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 5,
                    'justifiees' => 2,
                    'non_justifiees' => 3,
                ],
            ]);
    }

    public function test_peut_generer_simulation_bem(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'niveau_scolaire' => '4AM',
        ]);

        $matiere = Matiere::factory()->create(['nom_fr' => 'Mathématiques']);
        $groupe = Groupe::factory()->create([
            'matiere_id' => $matiere->id,
            'niveau_scolaire' => '4AM',
        ]);

        Inscription::create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'groupe_id' => $groupe->id,
            'annee_scolaire' => '2025/2026',
            'date_inscription' => now(),
            'statut' => 'validée',
            'frais_inscription' => 0,
            'frais_paye' => true,
        ]);

        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'groupe_id' => $groupe->id,
            'note_sur' => 20,
        ]);

        Note::factory()->create([
            'tenant_id' => $this->tenant->id,
            'evaluation_id' => $evaluation->id,
            'eleve_id' => $eleve->id,
            'note' => 15.5,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/rapports/simulation-bem?eleve_id=' . $eleve->id)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'BEM',
                    'contexte' => '4AM',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'eleve' => ['id', 'nom', 'niveau'],
                    'moyenne_simulee',
                    'mention_simulee',
                    'detail',
                ],
            ]);
    }

    public function test_peut_generer_simulation_bac(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'niveau_scolaire' => '3AS',
        ]);

        $matiere = Matiere::factory()->create(['nom_fr' => 'Mathématiques']);
        $groupe = Groupe::factory()->create([
            'matiere_id' => $matiere->id,
            'niveau_scolaire' => '3AS',
        ]);

        Inscription::create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'groupe_id' => $groupe->id,
            'annee_scolaire' => '2025/2026',
            'date_inscription' => now(),
            'statut' => 'validée',
            'frais_inscription' => 0,
            'frais_paye' => true,
        ]);

        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'groupe_id' => $groupe->id,
            'note_sur' => 20,
        ]);

        Note::factory()->create([
            'tenant_id' => $this->tenant->id,
            'evaluation_id' => $evaluation->id,
            'eleve_id' => $eleve->id,
            'note' => 14.0,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/rapports/simulation-bac?eleve_id=' . $eleve->id . '&filiere=sciences')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'BAC',
                    'contexte' => 'sciences',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'eleve',
                    'moyenne_simulee',
                    'mention_simulee',
                    'detail',
                ],
            ]);
    }

    public function test_validation_absences_pdf(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/rapports/absences-pdf')
            ->assertStatus(422);
    }

    public function test_validation_simulation_bac(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/rapports/simulation-bac?eleve_id=' . $eleve->id)
            ->assertStatus(422);
    }
}
