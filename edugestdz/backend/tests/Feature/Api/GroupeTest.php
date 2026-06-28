<?php
namespace Tests\Feature\Api;

use App\Models\{Groupe, Eleve, User, Tenant, Role, Matiere};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupeTest extends TestCase
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

    public function test_liste_groupes(): void
    {
        Groupe::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/groupes')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data', 'meta' => ['total']])
             ->assertJsonPath('meta.total', 3);
    }

    public function test_creer_groupe_valide(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $data = [
            'matiere_id'      => $matiere->id,
            'nom'             => 'Maths 3AS G1',
            'niveau_scolaire' => '3AS',
            'capacite_max'    => 20,
        ];

        $this->withToken($this->token)
             ->postJson('/api/v1/groupes', $data)
             ->assertStatus(201)
             ->assertJsonPath('data.nom', 'Maths 3AS G1')
             ->assertJsonPath('data.capacite_max', 20);

        $this->assertDatabaseHas('groupes', ['nom' => 'Maths 3AS G1']);
    }

    public function test_validation_matiere_requise(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/v1/groupes', [
                 'nom'             => 'Sans matiere',
                 'niveau_scolaire' => '3AS',
             ])
             ->assertStatus(422);
    }

    public function test_obtenir_groupe_par_id(): void
    {
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/groupes/{$groupe->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.groupe.id', $groupe->id);
    }

    public function test_modifier_groupe(): void
    {
        $groupe = Groupe::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'capacite_max' => 15,
        ]);

        $this->withToken($this->token)
             ->putJson("/api/v1/groupes/{$groupe->id}", [
                 'capacite_max' => 25,
                 'statut'      => 'inactif',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.capacite_max', 25);
    }

    public function test_supprimer_groupe(): void
    {
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->deleteJson("/api/v1/groupes/{$groupe->id}")
             ->assertStatus(200);

        $this->withToken($this->token)
             ->getJson("/api/v1/groupes/{$groupe->id}")
             ->assertStatus(404);
    }

    public function test_capacite_groupe_limite_inscriptions(): void
    {
        $groupe = Groupe::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'capacite_max' => 1,
        ]);

        Eleve::factory()->count(2)->create(['tenant_id' => $this->tenant->id])
            ->each(fn($e) => $groupe->eleves()->attach($e->id, [
                'date_inscription' => now(),
                'statut'           => 'actif',
            ]));

        $this->withToken($this->token)
             ->getJson("/api/v1/groupes/{$groupe->id}")
             ->assertStatus(200);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreGrp    = Groupe::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
             ->getJson("/api/v1/groupes/{$autreGrp->id}")
             ->assertStatus(404);
    }
}
