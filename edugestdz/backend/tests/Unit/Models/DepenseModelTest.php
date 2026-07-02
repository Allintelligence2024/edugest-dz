<?php
namespace Tests\Unit\Models;

use App\Models\{Depense, Tenant};
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DepenseModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_scope_validees(): void
    {
        Depense::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'statut' => 'validee']);
        Depense::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'statut' => 'en_attente']);

        $this->assertEquals(3, Depense::validees()->count());
    }

    public function test_scope_periode(): void
    {
        Depense::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'mois' => 7, 'annee' => 2026, 'statut' => 'validee']);
        Depense::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'mois' => 6, 'annee' => 2026, 'statut' => 'validee']);

        $this->assertEquals(2, Depense::validees()->periode(7, 2026)->count());
    }

    public function test_categorie_libelle_retourne_string(): void
    {
        $libelle = Depense::categorieLibelle('salaires_enseignants');
        $this->assertIsString($libelle);
        $this->assertNotEmpty($libelle);
    }

    public function test_categorie_libelle_inconnue(): void
    {
        $libelle = Depense::categorieLibelle('categorie_inexistante');
        $this->assertIsString($libelle);
    }
}
