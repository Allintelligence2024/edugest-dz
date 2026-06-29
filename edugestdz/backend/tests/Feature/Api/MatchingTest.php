<?php
namespace Tests\Feature\Api;

use App\Models\{Eleve, Enseignant, Matiere, Groupe, Tenant, User, Role};
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
        config(['services.openai.key' => null]);
    }

    public function test_suggestions_endpoint(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'wilaya_id'       => 16,
            'niveau_scolaire' => '1AS',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'tenant_id'        => $this->tenant->id,
            'date_inscription' => now(),
            'statut'           => 'validée',
            'annee_scolaire'   => now()->year . '/' . (now()->year + 1),
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'statut'             => 'actif',
            'wilaya_id'          => 16,
            'experience_annees'  => 5,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/matching/suggestions?eleve_id={$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['enseignant', 'score', 'raisons', 'details'],
                ],
                'meta' => ['total', 'llm_used'],
            ]);
    }

    public function test_suggestions_invalid_eleve(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/matching/suggestions?eleve_id=00000000-0000-0000-0000-000000000000')
            ->assertStatus(422);
    }

    public function test_suggestions_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create(['statut' => 'actif']);

        $matiere = Matiere::factory()->create(['tenant_id' => $autreTenant->id]);

        $eleve = Eleve::factory()->create([
            'tenant_id'       => $autreTenant->id,
            'wilaya_id'       => 16,
            'niveau_scolaire' => '1AS',
        ]);

        $groupe = Groupe::factory()->create([
            'tenant_id'       => $autreTenant->id,
            'matiere_id'      => $matiere->id,
            'niveau_scolaire' => '1AS',
        ]);

        $eleve->groupes()->attach($groupe->id, [
            'tenant_id'        => $this->tenant->id,
            'date_inscription' => now(),
            'statut'           => 'validée',
            'annee_scolaire'   => now()->year . '/' . (now()->year + 1),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/matching/suggestions?eleve_id={$eleve->id}")
            ->assertStatus(404);
    }
}
