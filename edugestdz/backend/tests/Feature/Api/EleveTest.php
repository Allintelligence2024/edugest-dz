<?php
namespace Tests\Feature\Api;

use App\Models\{Eleve, User, Tenant, Role, Wilaya};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EleveTest extends TestCase
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

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_eleves(): void
    {
        Eleve::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/eleves')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data', 'meta' => ['total']])
             ->assertJsonPath('meta.total', 5);
    }

    public function test_creer_eleve_valide(): void
    {
        $data = [
            'nom'             => 'BENALI',
            'prenom'          => 'Ahmed',
            'date_naissance'  => '2008-05-15',
            'lieu_naissance'  => 'Alger',
            'sexe'            => 'M',
            'niveau_scolaire' => '3AS',
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/v1/eleves', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('data.nom', 'BENALI')
                 ->assertJsonPath('data.prenom', 'Ahmed');

        $this->assertDatabaseHas('eleves', [
            'nom'    => 'BENALI',
            'prenom' => 'Ahmed',
        ]);
    }

    public function test_validation_nom_requis(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/v1/eleves', [
                 'prenom'          => 'Ahmed',
                 'date_naissance'  => '2008-05-15',
                 'lieu_naissance'  => 'Alger',
                 'sexe'            => 'M',
                 'niveau_scolaire' => '3AS',
             ])
             ->assertStatus(422)
             ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_obtenir_eleve_par_id(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/eleves/{$eleve->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.eleve.id', $eleve->id);
    }

    public function test_modifier_eleve(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->token)
             ->putJson("/api/v1/eleves/{$eleve->id}", ['statut' => 'inactif'])
             ->assertStatus(200)
             ->assertJsonPath('data.statut', 'inactif');
    }

    public function test_supprimer_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->deleteJson("/api/v1/eleves/{$eleve->id}")
             ->assertStatus(200);

        $this->withToken($this->token)
             ->getJson("/api/v1/eleves/{$eleve->id}")
             ->assertStatus(404);
    }

    public function test_recherche_eleve(): void
    {
        Eleve::factory()->create(['tenant_id' => $this->tenant->id, 'nom' => 'AMARI']);
        Eleve::factory()->create(['tenant_id' => $this->tenant->id, 'nom' => 'BENALI']);

        $this->withToken($this->token)
             ->getJson('/api/v1/eleves?search=AMARI')
             ->assertStatus(200)
             ->assertJsonPath('meta.total', 1);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/eleves/{$autreEleve->id}")
             ->assertStatus(404);
    }
}
