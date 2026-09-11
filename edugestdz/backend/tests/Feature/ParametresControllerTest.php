<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametresControllerTest extends TestCase
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
        $this->token = auth('api')->fromUser($user);
    }

    public function test_get_parametres_retourne_200(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/parametres')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_update_parametres_nom_ecole(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['nom_ecole' => 'École El Feth Oran'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/parametres')
            ->assertJsonPath('data.nom_ecole', 'École El Feth Oran');
    }

    public function test_couleur_principale_validation(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['couleur_principale' => 'rouge'])
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['couleur_principale' => '#FF5733'])
            ->assertStatus(200);
    }

    public function test_wilaya_doit_etre_entre_1_et_48(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['wilaya_id' => 99])
            ->assertStatus(422);
    }

    public function test_get_parametres_sans_auth_retourne_401(): void
    {
        $this->getJson('/api/v1/parametres')->assertStatus(401);
    }
}
