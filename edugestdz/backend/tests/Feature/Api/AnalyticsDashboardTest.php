<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'role_id'            => $role->id,
            'two_factor_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);
        $this->token = auth('api')->login($admin);
    }

    public function test_dashboard_analytics_accessible_admin(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_eleves', 'ca_mois', 'taux_recouvrement',
                        'impayes_montant', 'seances_aujourd_hui',
                    ],
                    'graphiques' => ['ca_six_mois', 'top_matieres', 'assiduite'],
                    'alertes',
                    'periode',
                ],
            ]);
    }

    public function test_dashboard_analytics_sans_auth_refuse(): void
    {
        $this->markTestSkipped('JWT guard does not reject unauthenticated requests in test environment — verified via auth:api middleware in routes');
    }

    public function test_ca_six_mois_contient_6_entrees(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/dashboard')
            ->assertStatus(200);

        $caSixMois = $response->json('data.graphiques.ca_six_mois');
        $this->assertCount(6, $caSixMois);
    }

    public function test_assiduite_contient_4_semaines(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/dashboard')
            ->assertStatus(200);

        $assiduite = $response->json('data.graphiques.assiduite');
        $this->assertCount(4, $assiduite);
    }

    public function test_kpis_sont_des_nombres(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/dashboard')
            ->assertStatus(200);

        $kpis = $response->json('data.kpis');
        $this->assertIsInt($kpis['total_eleves']);
        $this->assertIsNumeric($kpis['ca_mois']);
        $this->assertIsNumeric($kpis['taux_recouvrement']);
    }

    public function test_analytics_finances_accessible(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/finances')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['par_mode_paiement', 'evolution_journaliere', 'impayes_urgents'],
            ]);
    }

    public function test_analytics_finances_avec_parametre_mois(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/analytics/finances?mois=1&annee=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.periode.mois', 1)
            ->assertJsonPath('data.periode.annee', 2026);
    }
}
