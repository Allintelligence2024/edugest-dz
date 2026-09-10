<?php

namespace Tests\Unit\Services;

use App\Models\Enseignant;
use App\Models\Tenant;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IRGAlgerienCompletTest extends TestCase
{
    use RefreshDatabase;

    private PaieService $service;
    private Tenant $tenant;
    private Enseignant $enseignant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaieService();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        $this->enseignant = Enseignant::factory()->create([
            'type_contrat' => 'vacataire',
            'taux_horaire' => 2500,
            'num_cnas' => 'CNAS-2024-001',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_salaire_15000_exonere(): void
    {
        $this->assertSame(0.0, $this->service->calculerIRG(15000));
    }

    public function test_salaire_20000_exact_exonere(): void
    {
        $this->assertSame(0.0, $this->service->calculerIRG(20000));
    }

    public function test_salaire_30000_tranche_23(): void
    {
        $irg = $this->service->calculerIRG(30000);
        $this->assertSame(2300.0, $irg);
    }

    public function test_salaire_40001_tranche_27(): void
    {
        $irg = $this->service->calculerIRG(40001);
        $attendu = round((40001 * 27 / 100) - 6200, 2);
        $this->assertSame($attendu, $irg);
    }

    public function test_salaire_60000_tranche_27(): void
    {
        $irg = $this->service->calculerIRG(60000);
        $this->assertSame(10000.0, $irg);
    }

    public function test_salaire_100000_tranche_30(): void
    {
        $irg = $this->service->calculerIRG(100000);
        $this->assertSame(21400.0, $irg);
    }

    public function test_salaire_200000_tranche_33(): void
    {
        $irg = $this->service->calculerIRG(200000);
        $this->assertSame(52600.0, $irg);
    }

    public function test_salaire_400000_tranche_35(): void
    {
        $irg = $this->service->calculerIRG(400000);
        $this->assertSame(120200.0, $irg);
    }

    public function test_irg_jamais_negatif(): void
    {
        $this->assertSame(0.0, $this->service->calculerIRG(0));
        $this->assertSame(0.0, $this->service->calculerIRG(-1000));
        $this->assertSame(0.0, $this->service->calculerIRG(-50000));
    }

    public function test_irg_non_decroissante(): void
    {
        $prec = -1.0;
        foreach ([0, 10000, 20000, 30000, 50000, 80000, 160000, 320000] as $base) {
            $irg = $this->service->calculerIRG($base);
            $this->assertGreaterThanOrEqual($prec, $irg, "IRG ne doit pas décroître à $base");
            $prec = $irg;
        }
    }

    public function test_cnas_9_pourcent_via_paie(): void
    {
        $mois = now()->month;
        $annee = now()->year;

        $paie = $this->service->calculerPaie($this->enseignant, $mois, $annee);

        $this->assertArrayHasKey('cnas', $paie);
        $this->assertArrayHasKey('salaire_base', $paie);

        $cnasAttendu = round($paie['salaire_base'] * 0.09, 2);
        $this->assertSame($cnasAttendu, $paie['cnas']);
    }

    public function test_net_a_payer_coherent(): void
    {
        $paie = $this->service->calculerPaie($this->enseignant, now()->month, now()->year);

        $this->assertArrayHasKey('salaire_base', $paie);
        $this->assertArrayHasKey('cnas', $paie);
        $this->assertArrayHasKey('irg', $paie);
        $this->assertArrayHasKey('salaire_net', $paie);

        $attendu = round($paie['salaire_base'] - $paie['cnas'] - $paie['irg'], 2);
        $this->assertSame($attendu, $paie['salaire_net']);
    }
}
