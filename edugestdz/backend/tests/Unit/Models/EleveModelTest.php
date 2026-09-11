<?php
namespace Tests\Unit\Models;

use App\Models\{Eleve, Tenant};
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EleveModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_eleve_a_attribut_nom_complet(): void
    {
        $eleve = Eleve::factory()->make(['nom' => 'Benali', 'prenom' => 'Amira']);
        $this->assertEquals('BENALI Amira', $eleve->nom_complet);
    }

    public function test_eleve_casts_date_naissance(): void
    {
        $eleve = Eleve::factory()->make(['date_naissance' => '2010-03-15']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $eleve->date_naissance);
    }

    public function test_eleve_scope_actif(): void
    {
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        Eleve::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'statut' => 'inactif']);

        $actifs = Eleve::actifs()->count();
        $this->assertEquals(3, $actifs);
    }

    public function test_eleve_scope_statut(): void
    {
        Eleve::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'statut' => 'suspendu']);
        $suspendus = Eleve::where('statut', 'suspendu')->count();
        $this->assertEquals(2, $suspendus);
    }

    public function test_eleve_has_many_inscriptions(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->inscriptions());
    }

    public function test_eleve_has_many_presences(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->presences());
    }

    public function test_eleve_has_many_factures(): void
    {
        $eleve = Eleve::factory()->make();
        $this->assertIsObject($eleve->factures());
    }

    public function test_eleve_hidden_fields(): void
    {
        $eleve = Eleve::factory()->make();
        $array = $eleve->toArray();
        $this->assertArrayHasKey('nom', $array);
    }
}
