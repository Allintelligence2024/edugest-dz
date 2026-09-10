<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Eleve, Evaluation, Note, Groupe, Matiere};
use App\Services\DiagnosticService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiagnosticService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->service = app(DiagnosticService::class);
    }

    public function test_eleve_bonnes_notes_score_faible(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->creerNotes($eleve, 18, 3);

        $resultat = $this->service->analyserEleve($eleve->id);

        $this->assertLessThan(50, $resultat->score_risque);
    }

    public function test_eleve_mauvaises_notes_score_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->creerNotes($eleve, 3, 5);

        $resultat = $this->service->analyserEleve($eleve->id);

        $this->assertGreaterThan(30, $resultat->score_risque);
    }

    public function test_score_entre_0_et_100(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->analyserEleve($eleve->id);

        $this->assertGreaterThanOrEqual(0, $resultat->score_risque);
        $this->assertLessThanOrEqual(100, $resultat->score_risque);
    }

    public function test_resultat_contient_facteurs(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->analyserEleve($eleve->id);

        $this->assertNotNull($resultat->niveau_global);
        $this->assertIsArray($resultat->matieres_en_danger);
    }

    public function test_dashboard_accessible(): void
    {
        $dashboard = $this->service->getDashboard($this->tenant->id);
        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('total_analyses', $dashboard);
        $this->assertArrayHasKey('par_niveau', $dashboard);
    }

    private function creerNotes(Eleve $eleve, float $valeur, int $nb): void
    {
        $matiere = Matiere::factory()->create();
        $groupe = Groupe::factory()->create([
            'matiere_id' => $matiere->id,
            'tenant_id' => $this->tenant->id,
        ]);
        \DB::table('inscriptions')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'groupe_id' => $groupe->id,
            'annee_scolaire' => '2025/2026',
            'date_inscription' => now(),
            'statut' => 'validée',
            'frais_inscription' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 0; $i < $nb; $i++) {
            $eval = Evaluation::factory()->create([
                'tenant_id' => $this->tenant->id,
                'groupe_id' => $groupe->id,
                'note_sur' => 20,
            ]);
            Note::factory()->create([
                'tenant_id' => $this->tenant->id,
                'evaluation_id' => $eval->id,
                'eleve_id' => $eleve->id,
                'note' => $valeur,
            ]);
        }
    }
}
