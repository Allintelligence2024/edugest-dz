<?php

namespace Tests\Feature\Api;

use App\Models\{Bulletin, Eleve, Groupe, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class BulletinTest extends TestCase
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
    }

    public function test_liste_bulletins(): void
    {
        Bulletin::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $this->withToken($this->token)->getJson('/api/v1/bulletins')->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_generer_bulletins(): void
    {
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/bulletins/generer', [
                'groupe_id'      => $groupe->id,
                'trimestre'      => 'T1',
                'annee_scolaire' => '2025-2026',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_pdf_bulletin(): void
    {
        $bulletin = Bulletin::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->withToken($this->token)->getJson("/api/v1/bulletins/{$bulletin->id}/pdf")->assertStatus(200);
    }

    public function test_envoyer_bulletin(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $bulletin = Bulletin::factory()->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id]);
        $this->withToken($this->token)->postJson("/api/v1/bulletins/{$bulletin->id}/envoyer")->assertStatus(200);
    }
}
