<?php
namespace Tests\Unit\Services;

use App\Models\{Enseignant, Seance, Cours, Groupe, Matiere, Tenant};
use App\Services\PaieService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaieService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaieService();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_calcul_irg_exoneration_smig(): void
    {
        $this->assertEquals(0.0, $this->service->calculerIRG(20000));
        $this->assertEquals(0.0, $this->service->calculerIRG(0));
        $this->assertEquals(0.0, $this->service->calculerIRG(-5000));
    }

    public function test_calcul_irg_tranche_23_pourcent(): void
    {
        $irg = $this->service->calculerIRG(30000);
        $this->assertEquals(round((30000 * 0.23) - 4600, 2), $irg);
    }

    public function test_calcul_irg_tranche_27_pourcent(): void
    {
        $irg = $this->service->calculerIRG(60000);
        $this->assertEquals(round((60000 * 0.27) - 6200, 2), $irg);
    }

    public function test_calcul_irg_tranche_35_pourcent(): void
    {
        $irg = $this->service->calculerIRG(400000);
        $this->assertEquals(round((400000 * 0.35) - 19800, 2), $irg);
    }

    public function test_calcul_paie_vacataire(): void
    {
        $enseignant = Enseignant::factory()->create([
            'type_contrat' => 'vacataire',
            'taux_horaire' => 1500,
            'tenant_id'    => $this->tenant->id,
        ]);

        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe  = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $cours   = Cours::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'matiere_id'    => $matiere->id,
            'groupe_id'     => $groupe->id,
            'heure_debut'   => '09:00',
            'heure_fin'     => '11:00',
            'statut'        => 'actif',
        ]);
        Seance::factory()->create([
            'cours_id'   => $cours->id,
            'date_seance' => now()->startOfMonth()->addDays(2),
            'statut'     => 'terminée',
            'tenant_id'  => $this->tenant->id,
        ]);

        $result = $this->service->calculerPaie($enseignant, now()->month, now()->year);
        $this->assertEquals(2, $result['heures_travaillees']);
        $this->assertEquals(3000, $result['salaire_base']);
        $this->assertArrayHasKey('irg', $result);
        $this->assertArrayHasKey('cnas', $result);
        $this->assertArrayHasKey('salaire_net', $result);
        $this->assertGreaterThan(0, $result['salaire_net']);
    }

    public function test_calcul_paie_sans_seances(): void
    {
        $enseignant = Enseignant::factory()->create([
            'type_contrat' => 'vacataire',
            'taux_horaire' => 1500,
            'tenant_id'    => $this->tenant->id,
        ]);

        $result = $this->service->calculerPaie($enseignant, now()->month, now()->year);
        $this->assertEquals(0, $result['heures_travaillees']);
        $this->assertEquals(0, $result['salaire_base']);
    }

    public function test_calcul_heures_mensuelles(): void
    {
        $enseignant = Enseignant::factory()->create([
            'type_contrat' => 'vacataire',
            'taux_horaire' => 1000,
            'tenant_id'    => $this->tenant->id,
        ]);

        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe  = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $cours   = Cours::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'matiere_id'    => $matiere->id,
            'groupe_id'     => $groupe->id,
            'heure_debut'   => '10:00',
            'heure_fin'     => '12:00',
            'statut'        => 'actif',
        ]);

        foreach ([1, 8, 15, 22] as $day) {
            Seance::factory()->create([
                'cours_id'    => $cours->id,
                'date_seance' => now()->startOfMonth()->addDays($day),
                'statut'      => 'terminée',
                'tenant_id'   => $this->tenant->id,
            ]);
        }

        $heures = $this->service->calculerHeures($enseignant, now()->month, now()->year);
        $this->assertEquals(8.0, $heures);
    }
}
