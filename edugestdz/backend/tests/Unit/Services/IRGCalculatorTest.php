<?php
namespace Tests\Unit\Services;

use App\Services\PaieService;
use Tests\TestCase;

class IRGCalculatorTest extends TestCase
{
    private PaieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaieService();
    }

    public function test_smig_exonere_irg(): void
    {
        $this->assertEquals(0.0, $this->service->calculerIRG(20000));
        $this->assertEquals(0.0, $this->service->calculerIRG(0));
        $this->assertEquals(0.0, $this->service->calculerIRG(15000));
    }

    public function test_tranche_23_pourcent(): void
    {
        $irg = $this->service->calculerIRG(30000);
        $attendu = (30000 * 0.23) - 4600;
        $this->assertEquals(round($attendu, 2), $irg);
    }

    public function test_tranche_27_pourcent(): void
    {
        $irg = $this->service->calculerIRG(60000);
        $attendu = (60000 * 0.27) - 6200;
        $this->assertEquals(round($attendu, 2), $irg);
    }

    public function test_haute_tranche_35_pourcent(): void
    {
        $irg = $this->service->calculerIRG(400000);
        $attendu = (400000 * 0.35) - 19800;
        $this->assertEquals(round($attendu, 2), $irg);
    }

    public function test_irg_jamais_negatif(): void
    {
        $this->assertGreaterThanOrEqual(0.0, $this->service->calculerIRG(0));
        $this->assertGreaterThanOrEqual(0.0, $this->service->calculerIRG(-1000));
    }
}
