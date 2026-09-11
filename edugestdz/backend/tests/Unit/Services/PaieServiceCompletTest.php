<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Enseignant, Paie, User};
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieServiceCompletTest extends TestCase
{
    use RefreshDatabase;

    private PaieService $service;
    private Tenant $tenant;
    private Enseignant $enseignant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->service = app(PaieService::class);
        $this->enseignant = Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_calculer_heures_enseignant(): void
    {
        $heures = $this->service->calculerHeures($this->enseignant, 6, 2026);
        $this->assertIsFloat($heures);
        $this->assertGreaterThanOrEqual(0, $heures);
    }

    public function test_calculer_irg_sans_revenu(): void
    {
        $irg = $this->service->calculerIRG(0);
        $this->assertEquals(0.0, $irg);
    }

    public function test_calculer_irg_petit_salaire(): void
    {
        $irg = $this->service->calculerIRG(10000);
        $this->assertEquals(0.0, $irg);
    }

    public function test_calculer_irg_moyen_salaire(): void
    {
        $irg = $this->service->calculerIRG(80000);
        $this->assertGreaterThan(0, $irg);
    }

    public function test_generer_bulletin_pdf(): void
    {
        $paie = Paie::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enseignant_id' => $this->enseignant->id,
            'mois' => 6,
            'annee' => 2026,
            'salaire_base' => 40000,
        ]);

        $resultat = $this->service->genererBulletinPDF($paie);
        $this->assertIsString($resultat);
    }
}
