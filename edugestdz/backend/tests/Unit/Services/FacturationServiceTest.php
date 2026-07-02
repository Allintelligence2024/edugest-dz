<?php
namespace Tests\Unit\Services;

use App\Models\{Eleve, Facture, Paiement, Tenant};
use App\Services\FacturationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacturationService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FacturationService::class);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    // TODO: genererNumeroFacture est privée
    // public function test_generer_numero_facture_format(): void
    // {
    //     $numero = $this->service->genererNumeroFacture();
    //     $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4,6}$/', $numero);
    // }

    // TODO: ces méthodes n'existent pas dans le service
    // public function test_calcul_tva_nulle_par_defaut(): void
    // {
    //     $tva = $this->service->calculerTVA(10000);
    //     $this->assertEquals(0, $tva);
    // }

    public function test_creer_facture_retourne_instance(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $facture = $this->service->creerFacture([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'mois' => 7,
            'annee' => 2026,
            'lignes' => [
                ['description' => 'Scolarité juillet', 'prix_unitaire' => 5000, 'quantite' => 1, 'total' => 5000],
            ],
        ]);

        $this->assertInstanceOf(Facture::class, $facture);
        $this->assertEquals(5000, $facture->total_ttc);
    }

    // TODO: getTableauBord utilise des fonctions PostgreSQL (DATE_TRUNC, EXTRACT) et SQLite ne les supporte pas
    // public function test_get_tableau_bord_retourne_structure(): void
    // {
    //     $dashboard = $this->service->getTableauBord();
    //     $this->assertArrayHasKey('ca_mois', $dashboard);
    //     $this->assertArrayHasKey('ca_annee', $dashboard);
    //     $this->assertArrayHasKey('impayes', $dashboard);
    // }

    public function test_enregistrer_paiement_retourne_instance(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $facture = $this->service->creerFacture([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'mois' => 7,
            'annee' => 2026,
            'lignes' => [
                ['description' => 'Scolarité juillet', 'prix_unitaire' => 5000, 'quantite' => 1, 'total' => 5000],
            ],
        ]);

        $paiement = $this->service->enregistrerPaiement([
            'tenant_id' => $this->tenant->id,
            'facture_id' => $facture->id,
            'montant' => 5000,
            'mode_paiement' => 'espece',
            'date_paiement' => now(),
        ]);

        $this->assertInstanceOf(Paiement::class, $paiement);
    }
}
