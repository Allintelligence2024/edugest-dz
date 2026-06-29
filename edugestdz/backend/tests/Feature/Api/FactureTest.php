<?php

namespace Tests\Feature\Api;

use App\Models\{Facture, Eleve, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FactureTest extends TestCase
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

    public function test_liste_factures(): void
    {
        Facture::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/factures')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_creer_facture(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/factures', [
                'eleve_id'       => $eleve->id,
                'mois'           => now()->month,
                'annee'          => now()->year,
                'sous_total'     => 15000,
                'total_ttc'      => 15000,
                'statut'         => 'emise',
                'lignes'         => [
                    ['description' => 'Cours Maths 3AS', 'quantite' => 1, 'prix_unitaire' => 15000, 'total' => 15000],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_pdf_facture(): void
    {
        $facture = Facture::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/factures/{$facture->id}/pdf")
            ->assertStatus(200);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $facture = Facture::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/factures/{$facture->id}")
            ->assertStatus(404);
    }
}
