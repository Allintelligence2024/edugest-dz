<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthFlowCompletTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succes_retourne_token(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'statut' => 'actif',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'access_token', 'expires_in', 'user' => ['id', 'nom', 'prenom', 'role']]);
    }

    public function test_login_mauvais_mdp_retourne_401(): void
    {
        $tenant = Tenant::factory()->create();
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'password' => bcrypt('CorrectPass@2026!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ])->assertStatus(401);
    }

    public function test_compte_inactif_refuse_connexion(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'statut' => 'inactif',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_logout_blackliste_token(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);
        config(['tenant.current_id' => $tenant->id]);
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_refresh_token_valide(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'access_token']);
    }

    public function test_validation_email_requis(): void
    {
        $this->postJson('/api/v1/auth/login', ['password' => 'test'])
            ->assertStatus(422);
    }

    public function test_validation_password_requis(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'test@test.com'])
            ->assertStatus(422);
    }
}
