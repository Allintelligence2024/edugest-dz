<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $this->tenantId = $tenant->id;
        config(['tenant.current_id' => $tenant->id]);
        $role = Role::firstOrCreate(['nom' => 'admin']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->fromUser($user);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_recherche_trop_court_retourne_0(): void
    {
        $response = $this->getJson('/api/v1/search?q=a', $this->authHeaders());

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total' => 0,
            ]);
    }

    public function test_recherche_eleve(): void
    {
        DB::table('eleves')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantId,
            'numero_inscription' => 'ELE-001',
            'nom' => 'Benali',
            'prenom' => 'Ahmed',
            'date_naissance' => '2010-01-15',
            'sexe' => 'M',
            'niveau_scolaire' => '3AM',
            'statut' => 'actif',
        ]);

        $response = $this->getJson('/api/v1/search?q=Benali', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('total', fn ($t) => $t >= 1)
            ->assertJsonPath('data.0.type', 'eleve');
    }

    public function test_recherche_enseignant(): void
    {
        DB::table('enseignants')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantId,
            'user_id' => null,
            'matricule' => 'ENS-001',
            'nom' => 'Khelifi',
            'prenom' => 'Sara',
            'statut' => 'actif',
        ]);

        $response = $this->getJson('/api/v1/search?q=Khelifi', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('total', fn ($t) => $t >= 1)
            ->assertJsonPath('data.0.type', 'enseignant');
    }

    public function test_recherche_matiere(): void
    {
        DB::table('matieres')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantId,
            'nom_fr' => 'Mathématiques',
            'couleur' => '#1E5EBC',
            'statut' => 'actif',
        ]);

        $response = $this->getJson('/api/v1/search?q=Math', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('total', fn ($t) => $t >= 1)
            ->assertJsonPath('data.0.type', 'matiere');
    }

    public function test_recherche_sans_auth_retourne_401(): void
    {
        $response = $this->getJson('/api/v1/search?q=test');

        $response->assertStatus(401);
    }
}
