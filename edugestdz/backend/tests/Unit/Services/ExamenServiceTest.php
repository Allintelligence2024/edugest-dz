<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, SessionExamen, SalleExamen, CandidatExamen, User, Role};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\ExamenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamenServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExamenService $service;
    private Tenant $tenant;
    private SessionExamen $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->service = app(ExamenService::class);

        $this->session = SessionExamen::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'BEM',
            'annee_scolaire' => '2025/2026',
            'date_debut' => '2026-06-01',
            'date_fin' => '2026-06-05',
            'wilaya' => 31,
            'statut' => 'brouillon',
        ]);
    }

    public function test_affecter_candidats_aux_salles(): void
    {
        SalleExamen::create([
            'session_id' => $this->session->id,
            'tenant_id' => $this->tenant->id,
            'nom' => 'Salle A',
            'capacite_totale' => 30,
            'nb_colonnes' => 5,
        ]);

        $candidat = CandidatExamen::create([
            'session_id' => $this->session->id,
            'tenant_id' => $this->tenant->id,
            'nom' => 'Test',
            'prenom' => 'Candidat',
        ]);

        $resultat = $this->service->affecterCandidatsAuxSalles($this->session->id);

        $this->assertArrayHasKey('affectes', $resultat);
        $this->assertEquals(1, $resultat['affectes']);
        $this->assertNotNull($candidat->fresh()->salle_id);
    }

    public function test_dashboard_session(): void
    {
        $dashboard = $this->service->getDashboard($this->session->id);

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('nb_candidats_total', $dashboard);
        $this->assertArrayHasKey('nb_salles', $dashboard);
        $this->assertArrayHasKey('pret_pour_examen', $dashboard);
    }

    public function test_affecter_surveillants_respecte_specialite(): void
    {
        $salle = SalleExamen::create([
            'session_id' => $this->session->id,
            'tenant_id' => $this->tenant->id,
            'nom' => 'Salle B',
            'capacite_totale' => 30,
            'nb_candidats_affectes' => 5,
            'nb_colonnes' => 5,
        ]);

        for ($i = 0; $i < 5; $i++) {
            CandidatExamen::create([
                'session_id' => $this->session->id,
                'tenant_id' => $this->tenant->id,
                'salle_id' => $salle->id,
                'nom' => 'Candidat',
                'prenom' => "Test$i",
            ]);
        }

        $roleSurv = Role::factory()->create(['nom' => 'surveillant']);
        $userSurv = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $roleSurv->id,
        ]);

        \DB::table('surveillants_examen')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'session_id' => $this->session->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $userSurv->id,
            'nom' => 'Surveillant',
            'prenom' => 'Test',
            'role' => 'surveillant',
            'disponible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultat = $this->service->affecterSurveillantsAuxSalles($this->session->id);

        $this->assertArrayHasKey('affectations', $resultat);
    }

    public function test_generer_convocation_candidat(): void
    {
        $candidat = CandidatExamen::create([
            'session_id' => $this->session->id,
            'tenant_id' => $this->tenant->id,
            'nom' => 'Test',
            'prenom' => 'Candidat',
        ]);

        $pdf = $this->service->genererConvocationCandidat($candidat->id);

        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }
}
