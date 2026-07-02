<?php

namespace Tests\Feature\Api;

use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_aujourdhui_retourne_liste_enseignants(): void
    {
        Enseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);

        $this->withToken($this->token)
            ->getJson('/api/v1/pointage/enseignants/aujourd-hui')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonStructure(['data' => ['date', 'enseignants', 'stats']]);
    }

    public function test_arrivee_manuelle_enregistree(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '08:00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'present');

        $this->assertDatabaseHas('pointage_enseignants', [
            'enseignant_id' => $enseignant->id,
            'heure_arrivee' => '08:00',
            'statut'        => 'present',
        ]);
    }

    public function test_arrivee_tardive_detecte_retard(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '09:30',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'retard');
    }

    public function test_double_arrivee_bloquee(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointageEnseignant::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'date'          => today(),
            'heure_arrivee' => '08:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '08:05',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_POINTE');
    }

    public function test_depart_sans_arrivee_bloque(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/depart", [
                'heure_depart' => '17:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAS_ARRIVEE');
    }

    public function test_historique_retourne_donnees(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointageEnseignant::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'date'          => today(),
            'heure_arrivee' => '08:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/pointage/enseignants/{$enseignant->id}/historique")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['meta' => ['stats']]);
    }

    public function test_badge_inconnu_retourne_404(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'BADGE-INCONNU-999',
                'type'      => 'entrée',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BADGE_INCONNU');
    }

    public function test_assigner_badge_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/absences/badges/assigner', [
                'eleve_id'  => $eleve->id,
                'badge_uid' => 'AB:CD:EF:01',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('badges', [
            'badge_uid'         => 'AB:CD:EF:01',
            'proprietaire_id'   => $eleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
        ]);
    }

    public function test_scan_badge_eleve_marque_present(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Badge::create([
            'tenant_id'         => $this->tenant->id,
            'badge_uid'         => 'AA:BB:CC:DD',
            'proprietaire_id'   => $eleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'AA:BB:CC:DD',
                'type'      => 'entrée',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'élève');

        $this->assertDatabaseHas('absences_journalieres', [
            'eleve_id'     => $eleve->id,
            'signale_par'  => 'badge',
        ]);
    }

    public function test_badge_autre_tenant_invisible(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        \DB::table('badges')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'         => $autreTenant->id,
            'badge_uid'         => 'AUTRE-TENANT-BADGE',
            'proprietaire_id'   => $autreEleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'AUTRE-TENANT-BADGE',
                'type'      => 'entrée',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BADGE_INCONNU');
    }
}
