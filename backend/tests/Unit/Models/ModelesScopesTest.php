<?php
namespace Tests\Unit\Models;

use App\Models\{Facture, Note, Paiement, Groupe, Tenant, Eleve, Evaluation, Matiere, Enseignant};
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModelesScopesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_facture_scope_par_eleve(): void
    {
        $eleve1 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve2 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Facture::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve1->id]);
        Facture::factory()->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve2->id]);

        $this->assertEquals(2, Facture::parEleve($eleve1->id)->count());
        $this->assertEquals(1, Facture::parEleve($eleve2->id)->count());
    }

    public function test_facture_scopes_statuts(): void
    {
        Facture::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'émise']);
        Facture::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'envoyée']);
        Facture::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'payée']);
        Facture::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'impayée']);

        $this->assertEquals(2, Facture::enCours()->count());
        $this->assertEquals(1, Facture::payees()->count());
        $this->assertEquals(1, Facture::impayees()->count());
    }

    public function test_note_scope_par_eleve(): void
    {
        $eleve1 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve2 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Note::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve1->id]);
        Note::factory()->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve2->id]);

        $this->assertEquals(3, Note::parEleve($eleve1->id)->count());
        $this->assertEquals(1, Note::parEleve($eleve2->id)->count());
    }

    public function test_note_scope_absents(): void
    {
        Note::factory()->create(['tenant_id' => $this->tenant->id, 'absent' => true]);
        Note::factory()->create(['tenant_id' => $this->tenant->id, 'absent' => true]);
        Note::factory()->create(['tenant_id' => $this->tenant->id, 'absent' => false]);

        $this->assertEquals(2, Note::absents()->count());
    }

    public function test_paiement_no_duplicate_belongs_to_tenant(): void
    {
        $reflection = new \ReflectionClass(Paiement::class);
        $traits = $reflection->getTraits();

        $this->assertArrayNotHasKey(\App\Traits\BelongsToTenant::class, $traits,
            'Paiement ne doit pas utiliser BelongsToTenant (déjà dans BaseModel)');
    }

    public function test_groupe_has_enseignant_id_in_fillable(): void
    {
        $groupe = new Groupe();
        $this->assertContains('enseignant_id', $groupe->getFillable());
    }

    public function test_groupe_scope_par_niveau(): void
    {
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'niveau_scolaire' => '3AS']);
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'niveau_scolaire' => '3AS']);
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'niveau_scolaire' => '2AS']);

        $this->assertEquals(2, Groupe::parNiveau('3AS')->count());
        $this->assertEquals(1, Groupe::parNiveau('2AS')->count());
    }

    public function test_groupe_scope_actifs(): void
    {
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'inactif']);

        $this->assertEquals(2, Groupe::actifs()->count());
    }

    public function test_groupe_has_enseignant_relation(): void
    {
        $groupe = new Groupe();
        $this->assertIsObject($groupe->enseignant());
    }
}
