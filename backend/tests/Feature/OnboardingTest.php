<?php
namespace Tests\Feature;
use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;
    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        $role = Role::firstOrCreate(['nom' => 'admin']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($user);
    }

    public function test_statut_retourne_progression(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['etape', 'complete', 'progression', 'etapes']);
    }

    public function test_avancer_sauvegarde_etape(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/avancer', ['etape' => 2])
            ->assertStatus(200)
            ->assertJsonPath('etape', 2);
    }

    public function test_etape_invalide_retourne_422(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/avancer', ['etape' => 99])
            ->assertStatus(422);
    }

    public function test_ignorer_marque_complete(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/ignorer')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertJsonPath('complete', true);
    }

    public function test_tester_notification_marque_complete(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/tester-notification')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertJsonPath('complete', true);
    }
}
