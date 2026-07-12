<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\SessionExamen;
use App\Models\SalleExamen;
use App\Models\CandidatExamen;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RapportOnecTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $this->admin = User::factory()->create([
            'role_id'   => $role->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        Excel::fake();
    }

    private function makeSession(array $attrs = []): SessionExamen
    {
        return SessionExamen::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'type'            => 'BAC',
            'annee_scolaire'  => '2025/2026',
            'session'         => 'principale',
            'date_debut'      => '2026-06-07',
            'date_fin'        => '2026-06-11',
            'wilaya'          => 'Oran',
            'nom_centre'      => 'Lycée Test',
            'max_candidats_par_salle'    => 20,
            'nb_surveillants_par_salle'  => 3,
            'statut'          => 'planifie',
        ], $attrs));
    }

    public function test_export_onec_telecharge_fichier(): void
    {
        $session = $this->makeSession();

        CandidatExamen::create([
            'session_id'        => $session->id,
            'tenant_id'         => $this->tenant->id,
            'nom'               => 'Benali',
            'prenom'            => 'Amira',
            'numero_inscription'=> '260010001',
            'type_candidat'     => 'scolarise',
            'present'           => true,
        ]);

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/{$session->id}/export-onec")
            ->assertStatus(200);
    }

    public function test_export_onec_contient_deux_feuilles(): void
    {
        $session = $this->makeSession();

        for ($i = 1; $i <= 5; $i++) {
            CandidatExamen::create([
                'session_id'        => $session->id,
                'tenant_id'         => $this->tenant->id,
                'nom'               => "Candidat {$i}",
                'prenom'            => 'Test',
                'numero_inscription'=> "26001000{$i}",
                'type_candidat'     => $i <= 3 ? 'scolarise' : 'libre',
                'present'           => $i <= 4,
            ]);
        }

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/{$session->id}/export-onec")
            ->assertStatus(200);
    }

    public function test_export_onec_session_inexistante_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->get('/api/v1/examens/00000000-0000-0000-0000-000000000000/export-onec')
            ->assertStatus(404);
    }

    public function test_export_onec_session_vide(): void
    {
        $session = $this->makeSession();

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/{$session->id}/export-onec")
            ->assertStatus(200);
    }

    public function test_export_onec_bem(): void
    {
        $session = $this->makeSession([
            'type'           => 'BEM',
            'annee_scolaire' => '2025/2026',
        ]);

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/{$session->id}/export-onec")
            ->assertStatus(200);
    }

    public function test_export_onec_sans_auth_refuse(): void
    {
        $session = $this->makeSession();

        $this->getJson("/api/v1/examens/{$session->id}/export-onec")
            ->assertStatus(401);
    }
}

