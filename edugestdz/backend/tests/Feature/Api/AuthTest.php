<?php
namespace Tests\Feature\Api;

use App\Models\{User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected User   $admin;
    protected Tenant $tenant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role         = Role::factory()->create(['nom' => 'admin']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
    }

    public function test_login_avec_credentials_valides(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'access_token',
                         'expires_in',
                         'user' => ['id', 'nom', 'prenom', 'role', 'tenant'],
                     ],
                 ])
                 ->assertJson(['success' => true]);
    }

    public function test_login_email_invalide(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'mauvais@email.com',
            'password' => 'password',
        ])->assertStatus(401)
          ->assertJson(['success' => false]);
    }

    public function test_login_mot_de_passe_invalide(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'mauvais_mdp',
        ])->assertStatus(401);
    }

    public function test_validation_email_requis(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_me_avec_token_valide(): void
    {
        $token = auth('api')->login($this->admin);

        $this->withToken($token)
             ->getJson('/api/v1/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('data.id', $this->admin->id);
    }

    public function test_logout(): void
    {
        $token = auth('api')->login($this->admin);

        $this->withToken($token)
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }

    public function test_compte_inactif_bloque(): void
    {
        $this->admin->update(['statut' => 'suspendu']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ])->assertStatus(403);
    }
}
