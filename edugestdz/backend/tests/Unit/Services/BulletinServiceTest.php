<?php
namespace Tests\Unit\Services;

use App\Models\{Eleve, Enseignant, Evaluation, Groupe, Matiere, Note, Tenant, Cours};
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletinServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulletinService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulletinService::class);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    // TODO: getAppreciation est privée
    // public function test_appreciation_insuffisant(): void
    // {
    //     $this->assertEquals('Insuffisant', $this->service->getAppreciation(4.5));
    // }

    public function test_calculer_moyenne_retourne_float(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $cours = Cours::factory()->create([
            'tenant_id' => $this->tenant->id,
            'groupe_id' => $groupe->id,
            'matiere_id' => $matiere->id,
            'statut' => 'actif',
        ]);
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'groupe_id' => $groupe->id,
            'type_eval' => 'devoir_classe',
        ]);
        Note::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'evaluation_id' => $evaluation->id,
            'note' => 15,
        ]);

        $moyenne = $this->service->calculerMoyenne($eleve->id, $groupe->id, 'trimestre_1');
        $this->assertIsFloat($moyenne);
    }

    public function test_calculer_moyenne_sans_notes_retourne_zero(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);

        $moyenne = $this->service->calculerMoyenne($eleve->id, $groupe->id, 'trimestre_1');
        $this->assertEquals(0.0, $moyenne);
    }
}
