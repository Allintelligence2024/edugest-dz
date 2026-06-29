<?php

namespace Tests\Feature\Api;

use App\Models\{Paiement, Facture, Eleve, User, Tenant, Role};
use App\Services\Paiement\SatimGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PaiementEnLigneTest extends TestCase
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

    public function test_initier_paiement_cib(): void
    {
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_ttc' => 15000,
            'statut'    => 'emise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'    => $facture->id,
                'type_paiement' => 'cib',
                'montant'       => 15000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['redirect_url', 'order_id']]);
    }

    public function test_initier_paiement_baridimob(): void
    {
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_ttc' => 8000,
            'statut'    => 'emise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'    => $facture->id,
                'type_paiement' => 'baridimob',
                'montant'       => 8000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reference', 'montant']]);
    }

    public function test_retour_paiement_succes(): void
    {
        $paiement = Paiement::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'reference_trans' => 'TEST-REF-001',
            'order_id'        => 'SANDBOX_123',
            'statut'          => 'en_attente',
            'mode'            => 'en_ligne',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/retour?reference=TEST-REF-001&satim_order_id=SANDBOX_123')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_retour_paiement_echec(): void
    {
        $paiement = Paiement::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'reference_trans' => 'TEST-REF-002',
            'statut'          => 'en_attente',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/retour?reference=TEST-REF-002&echec=1')
            ->assertStatus(500);
    }

    public function test_callback_satim(): void
    {
        $paiement = Paiement::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'reference_trans' => 'PAY-TEST-123',
            'order_id'        => 'SANDBOX_456',
            'statut'          => 'en_attente',
            'mode'            => 'en_ligne',
        ]);

        $this->postJson('/api/v1/paiements/online/callback', [
            'orderId'      => 'SANDBOX_456',
            'orderNumber'  => 'PAY-TEST-123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);
    }

    public function test_initier_paiement_facture_deja_payee(): void
    {
        $facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_ttc' => 10000,
            'statut'    => 'payee',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'    => $facture->id,
                'type_paiement' => 'cib',
                'montant'       => 10000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'E004');
    }

    public function test_isolation_tenant_paiement_en_ligne(): void
    {
        $autreTenant = Tenant::factory()->create();
        $facture = Facture::factory()->create([
            'tenant_id' => $autreTenant->id,
            'total_ttc' => 5000,
            'statut'    => 'emise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'    => $facture->id,
                'type_paiement' => 'cib',
            ])
            ->assertStatus(404);
    }
}
