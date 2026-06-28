<?php
namespace Tests\Unit\Services;

use App\Models\{Eleve, Enseignant, Matiere, Groupe, Tenant, User, Role};
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchingService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchingService();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_calculate_score_perfect_match(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'wilaya_id'        => 16,
            'niveau_scolaire'  => '1AS',
            'budget_mensuel'   => 2000,
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'date_inscription' => now(),
            'statut'           => 'validée',
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'wilaya_id'          => 16,
            'taux_horaire'       => 1500,
            'experience_annees'  => 10,
            'disponibilites'     => [['jour' => 'lundi', 'debut' => '09:00', 'fin' => '12:00']],
        ]);

        $enseignant->matieres()->attach($matiere->id, [
            'niveau_scolaire' => '1AS',
            'est_principal'   => true,
        ]);

        $result = $this->service->calculateScore($eleve, $enseignant);

        $this->assertGreaterThanOrEqual(0.9, $result['total']);
        $this->assertNotEmpty($result['raisons']);
    }

    public function test_calculate_score_no_match(): void
    {
        $matiereEleve = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $matiereEns   = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'wilaya_id'        => 1,
            'niveau_scolaire'  => '1AP',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiereEleve->id,
            'niveau_scolaire' => '1AP',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'date_inscription' => now(),
            'statut'           => 'validée',
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'wilaya_id'          => 48,
            'taux_horaire'       => 5000,
            'experience_annees'  => 1,
        ]);

        $enseignant->matieres()->attach($matiereEns->id, [
            'niveau_scolaire' => '3AS',
            'est_principal'   => true,
        ]);

        $result = $this->service->calculateScore($eleve, $enseignant);

        $this->assertLessThan(0.5, $result['total']);
    }

    public function test_calculate_score_partial(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'wilaya_id'        => 16,
            'niveau_scolaire'  => '1AS',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'date_inscription' => now(),
            'statut'           => 'validée',
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'wilaya_id'          => 15,
            'taux_horaire'       => 2000,
            'experience_annees'  => 4,
        ]);

        $enseignant->matieres()->attach($matiere->id, [
            'niveau_scolaire' => '2AS',
            'est_principal'   => true,
        ]);

        $result = $this->service->calculateScore($eleve, $enseignant);

        $this->assertGreaterThanOrEqual(0.3, $result['total']);
        $this->assertLessThanOrEqual(0.7, $result['total']);
    }

    public function test_suggestions_returns_top_n(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'wilaya_id'        => 16,
            'niveau_scolaire'  => '1AS',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'date_inscription' => now(),
            'statut'           => 'validée',
        ]);

        Enseignant::factory()->count(5)->create([
            'tenant_id'  => $this->tenant->id,
            'statut'     => 'actif',
            'wilaya_id'  => 16,
        ])->each(fn($ens) => $ens->matieres()->attach($matiere->id, [
            'niveau_scolaire' => '1AS',
            'est_principal'   => true,
        ]));

        $result = $this->service->getSuggestions($eleve->id, 3);

        $this->assertCount(3, $result['data']);
        $this->assertArrayHasKey('enseignant', $result['data'][0]);
        $this->assertArrayHasKey('score', $result['data'][0]);
        $this->assertArrayHasKey('raisons', $result['data'][0]);
        $this->assertArrayHasKey('details', $result['data'][0]);
    }

    public function test_raisons_are_generated(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'wilaya_id'        => 16,
            'niveau_scolaire'  => '1AS',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'date_inscription' => now(),
            'statut'           => 'validée',
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'wilaya_id'          => 16,
            'taux_horaire'       => 1000,
            'experience_annees'  => 12,
            'disponibilites'     => [['jour' => 'lundi', 'debut' => '09:00', 'fin' => '12:00']],
        ]);

        $enseignant->matieres()->attach($matiere->id, [
            'niveau_scolaire' => '1AS',
            'est_principal'   => true,
        ]);

        $result = $this->service->calculateScore($eleve, $enseignant);

        $this->assertNotEmpty($result['raisons']);
        foreach ($result['raisons'] as $raison) {
            $this->assertIsString($raison);
            $this->assertNotEmpty($raison);
        }
    }
}
