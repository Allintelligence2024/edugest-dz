<?php
namespace Tests\Feature\Api;

use App\Models\{Enseignant, User, Tenant, Role, Matiere};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EnseignantTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_enseignants(): void
    {
        Enseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/enseignants')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data', 'meta' => ['total']])
             ->assertJsonPath('meta.total', 3);
    }

    public function test_creer_enseignant_valide(): void
    {
        $data = [
            'nom'          => 'HADJ',
            'prenom'       => 'Mohamed',
            'sexe'         => 'M',
            'telephone'    => '0555123456',
            'email'        => 'm.hadj@example.com',
            'type_contrat' => 'vacataire',
            'taux_horaire' => 1500,
        ];

        $this->withToken($this->token)
             ->postJson('/api/v1/enseignants', $data)
             ->assertStatus(201)
             ->assertJsonPath('data.nom', 'HADJ')
             ->assertJsonPath('data.prenom', 'Mohamed');

        $this->assertDatabaseHas('enseignants', ['email' => 'm.hadj@example.com']);
    }

    public function test_validation_email_unique(): void
    {
        Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'doublon@example.com',
        ]);

        $this->withToken($this->token)
             ->postJson('/api/v1/enseignants', [
                 'nom'          => 'TEST',
                 'prenom'       => 'Test',
                 'email'        => 'doublon@example.com',
                 'telephone'    => '0555123456',
                 'type_contrat' => 'vacataire',
                 'taux_horaire' => 1000,
             ])
             ->assertStatus(422);
    }

    public function test_obtenir_enseignant_par_id(): void
    {
        $ens = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/enseignants/{$ens->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.enseignant.id', $ens->id);
    }

    public function test_modifier_enseignant(): void
    {
        $ens = Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->token)
             ->putJson("/api/v1/enseignants/{$ens->id}", [
                 'taux_horaire' => 2000,
                 'statut'       => 'suspendu',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.statut', 'suspendu');
    }

    public function test_supprimer_enseignant(): void
    {
        $ens = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->deleteJson("/api/v1/enseignants/{$ens->id}")
             ->assertStatus(200);

        $this->withToken($this->token)
             ->getJson("/api/v1/enseignants/{$ens->id}")
             ->assertStatus(404);
    }

    public function test_statistiques_enseignant(): void
    {
        $ens = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/enseignants/{$ens->id}/statistiques")
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data']);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreEns    = Enseignant::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/enseignants/{$autreEns->id}")
             ->assertStatus(404);
    }

    public function test_toggle_statut_enseignant(): void
    {
        $ens = Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->token)
             ->postJson("/api/v1/enseignants/{$ens->id}/toggle-statut")
             ->assertStatus(200);

        $ens->refresh();
        $this->assertEquals('suspendu', $ens->statut);
    }
}
