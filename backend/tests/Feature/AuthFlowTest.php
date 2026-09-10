<?php
namespace Tests\Feature;
use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_retourne_token_jwt(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role   = Role::firstOrCreate(['nom' => 'admin']);
        $user   = User::factory()->create([
            'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'password'  => bcrypt('MonMotDePasse@2026'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'MonMotDePasse@2026',
        ])->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_login_mauvais_mot_de_passe_retourne_401(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('correct')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'mauvais',
        ])->assertStatus(401);
    }

    public function test_me_avec_token_valide(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $token  = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_me_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_invalide_le_token(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $token  = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_forgot_password_retourne_200_meme_si_email_inconnu(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'inconnu@test.com',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }
}
