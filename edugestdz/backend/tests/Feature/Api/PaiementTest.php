<?php

namespace Tests\Feature\Api;

use App\Models\{Paiement, Facture, Eleve, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PaiementTest extends TestCase
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

    public function test_creer_paiement_especes(): void
    {
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_ttc' => 10000,
            'statut'    => 'émise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements', [
                'facture_id'    => $facture->id,
                'montant'       => 10000,
                'mode_paiement' => 'espèces',
                'date_paiement' => now()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_caisse_jour(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/caisse-jour')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $paiement = Paiement::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paiements/{$paiement->id}/recu")
            ->assertStatus(404);
    }
}
