<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Eleve, LmsCours, LmsChapitre, LmsLecon, LmsInscription, User, Role};
use App\Services\LmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private LmsService $service;
    private Tenant $tenant;
    private User $enseignant;
    private LmsCours $cours;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $role = Role::factory()->create(['nom' => 'enseignant']);
        $this->enseignant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
        ]);

        $this->service = app(LmsService::class);

        $this->cours = LmsCours::create([
            'tenant_id' => $this->tenant->id,
            'enseignant_id' => $this->enseignant->id,
            'titre' => 'Mathématiques 3ème AS',
            'matiere' => 'mathematiques',
            'publie' => true,
        ]);
    }

    public function test_inscrire_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $inscription = $this->service->inscrireEleve($this->cours->id, $eleve->id);

        $this->assertInstanceOf(LmsInscription::class, $inscription);
        $this->assertDatabaseHas('lms_inscriptions', [
            'cours_id' => $this->cours->id,
            'eleve_id' => $eleve->id,
        ]);
    }

    public function test_double_inscription_retourne_meme_record(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $insc1 = $this->service->inscrireEleve($this->cours->id, $eleve->id);
        $insc2 = $this->service->inscrireEleve($this->cours->id, $eleve->id);

        $this->assertEquals($insc1->id, $insc2->id);
    }

    public function test_progression_initialisee_a_zero(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $inscription = $this->service->inscrireEleve($this->cours->id, $eleve->id);

        $this->assertEquals(0, $inscription->progression_pct);
    }

    public function test_dashboard_accessible(): void
    {
        $dashboard = $this->service->getDashboard();

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('total_cours', $dashboard);
        $this->assertArrayHasKey('total_inscrits', $dashboard);
    }
}
