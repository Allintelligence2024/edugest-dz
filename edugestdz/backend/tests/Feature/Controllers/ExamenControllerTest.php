<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\SessionExamen;
use App\Models\SalleExamen;
use App\Models\CandidatExamen;
use App\Models\SurveiillantExamen;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ExamenControllerTest extends TestCase
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
    }

    private function makeSession(array $attrs = []): SessionExamen
    {
        return SessionExamen::create(array_merge([
            'tenant_id'    => $this->tenant->id,
            'type'         => 'BAC',
            'annee_scolaire'=> '2025/2026',
            'session'      => 'principale',
            'date_debut'   => '2026-06-07',
            'date_fin'     => '2026-06-11',
            'wilaya'       => 'Oran',
            'nom_centre'   => 'Lycée Test',
            'max_candidats_par_salle' => 20,
            'nb_surveillants_par_salle' => 3,
            'statut'       => 'planifie',
        ], $attrs));
    }

    public function test_lister_sessions(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/examens')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_creer_session_bac(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/examens', [
                'type'           => 'BAC',
                'annee_scolaire' => '2025/2026',
                'session'        => 'principale',
                'date_debut'     => '2026-06-07',
                'date_fin'       => '2026-06-11',
                'wilaya'         => 'Oran',
                'nom_centre'     => 'Lycée Ibn Khaldoun',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'BAC');
    }

    public function test_creer_session_bem(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/examens', [
                'type'           => 'BEM',
                'annee_scolaire' => '2025/2026',
                'session'        => 'principale',
                'date_debut'     => '2026-05-19',
                'date_fin'       => '2026-05-21',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'BEM');
    }

    public function test_ajouter_epreuve(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/epreuves", [
                'matiere'       => 'Mathématiques',
                'coefficient'   => 6,
                'date_epreuve'  => '2026-06-07',
                'moment'        => 'matin',
                'heure_debut'   => '08:30',
                'heure_fin'     => '12:30',
                'duree_minutes' => 240,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.matiere', 'Mathématiques');
    }

    public function test_ajouter_salle(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/salles", [
                'nom'             => 'Salle 01',
                'capacite_totale' => 20,
                'nb_rangees'      => 4,
                'nb_colonnes'     => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Salle 01');
    }

    public function test_ajouter_candidat(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/candidats", [
                'nom'                => 'Benali',
                'prenom'             => 'Amira',
                'numero_inscription' => '260010001',
                'type_candidat'      => 'scolarise',
            ])
            ->assertStatus(201);
    }

    public function test_algorithme_affectation_candidats(): void
    {
        $session = $this->makeSession();

        for ($s = 1; $s <= 3; $s++) {
            SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>"Salle 0{$s}",'capacite_totale'=>20]);
        }

        for ($i = 1; $i <= 45; $i++) {
            CandidatExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>"Candidat {$i}",'prenom'=>'Test','type_candidat'=>'scolarise']);
        }

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/affecter-candidats")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(45, CandidatExamen::where('session_id',$session->id)->whereNotNull('salle_id')->count());
    }

    public function test_algorithme_affectation_surveillants_respecte_specialite(): void
    {
        $session = $this->makeSession();

        SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Salle 01','capacite_totale'=>20,'nb_candidats_affectes'=>20]);

        \App\Models\EpreuveExamen::create(['session_id'=>$session->id,'matiere'=>'Mathématiques','coefficient'=>6,'date_epreuve'=>'2026-06-07','moment'=>'matin','heure_debut'=>'08:30','heure_fin'=>'12:30']);

        $survMaths = SurveiillantExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'user_id'=>$this->admin->id,'nom'=>'Prof','prenom'=>'Maths','specialite'=>'Mathématiques','role'=>'surveillant','disponible'=>true]);
        $roleEns = Role::factory()->create(['nom' => 'enseignant']);
        for ($i = 2; $i <= 4; $i++) {
            $u = User::factory()->create(['role_id'=>$roleEns->id,'tenant_id'=>$this->tenant->id]);
            SurveiillantExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'user_id'=>$u->id,'nom'=>"Surv{$i}",'prenom'=>'Test','specialite'=>'Physique','role'=>'surveillant','disponible'=>true]);
        }

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/{$session->id}/affecter-surveillants")
            ->assertStatus(200);

        $this->assertNull($survMaths->fresh()->salle_id);
    }

    public function test_afficher_dashboard_session(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/examens/{$session->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['success','data','dashboard']);
    }

    public function test_generer_pdf_feuille_presence(): void
    {
        $session = $this->makeSession();
        $salle = SalleExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Salle 01','capacite_totale'=>20]);

        $this->actingAs($this->admin, 'api')
            ->get("/api/v1/examens/salles/{$salle->id}/feuille-presence")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_marquer_presence_candidat(): void
    {
        $session  = $this->makeSession();
        $candidat = CandidatExamen::create(['session_id'=>$session->id,'tenant_id'=>$session->tenant_id,'nom'=>'Test','prenom'=>'Test','type_candidat'=>'scolarise']);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/examens/candidats/{$candidat->id}/presence", ['present'=>true])
            ->assertStatus(200)
            ->assertJsonPath('data.present', true);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/examens')->assertStatus(401);
    }
}
