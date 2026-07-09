<?php

namespace Tests\Feature\Api\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithMarketplace(array $overrides = []): string
    {
        $tenantId = (string) Str::uuid();

        DB::table('tenants')->insert(array_merge([
            'id'                  => $tenantId,
            'nom_etablissement'   => 'Centre Test',
            'slug'                => 'centre-test-' . substr($tenantId, 0, 8),
            'type_etablissement'  => 'centre_cours',
            'wilaya_id'           => 16,
            'statut'              => 'actif',
            'created_at'          => now(),
            'updated_at'          => now(),
        ], $overrides));

        DB::table('tenant_modules')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'module_key' => 'marketplace',
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tenantId;
    }

    public function test_recherche_returns_tenants_with_marketplace_module(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Alpha']);

        $response = $this->getJson('/api/v1/marketplace/recherche');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_recherche_filtre_par_wilaya(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Alger', 'wilaya_id' => 16]);
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Oran', 'wilaya_id' => 31]);

        $response = $this->getJson('/api/v1/marketplace/recherche?wilaya=16');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $centre) {
            $this->assertEquals(16, $centre['wilaya'] ?? null);
        }
    }

    public function test_featured_retourne_tenants_actifs(): void
    {
        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id'                   => $tenantId,
            'nom_etablissement'    => 'Centre Featured',
            'slug'                 => 'centre-featured-' . substr($tenantId, 0, 8),
            'type_etablissement'   => 'centre_cours',
            'wilaya_id'            => 16,
            'statut'               => 'actif',
            'marketplace_featured' => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        DB::table('tenant_modules')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'module_key' => 'marketplace',
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/marketplace/featured');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data'])
            ->assertJsonPath('success', true);
    }

    public function test_stats_retourne_statistiques(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre 1']);
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre 2']);

        $response = $this->getJson('/api/v1/marketplace/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['total_centres', 'total_wilayas', 'message'],
            ])
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, $response->json('data.total_centres'));
    }

    public function test_profil_centre_avec_marketplace_actif(): void
    {
        $tenantId = $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Profil']);

        $response = $this->getJson("/api/v1/marketplace/centres/{$tenantId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['centre', 'offres', 'note_moyenne', 'nb_avis'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_profil_tenant_inexistant_retourne_404(): void
    {
        $fakeId = (string) Str::uuid();

        $response = $this->getJson("/api/v1/marketplace/centres/{$fakeId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_profil_tenant_sans_marketplace_retourne_404(): void
    {
        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id'                  => $tenantId,
            'nom_etablissement'   => 'Centre Sans Module',
            'slug'                => 'centre-sans-module-' . substr($tenantId, 0, 8),
            'type_etablissement'  => 'centre_cours',
            'wilaya_id'           => 16,
            'statut'              => 'actif',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $response = $this->getJson("/api/v1/marketplace/centres/{$tenantId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_recherche_sans_auth_accessible(): void
    {
        $this->getJson('/api/v1/marketplace/recherche')->assertStatus(200);
    }

    public function test_stats_sans_auth_accessible(): void
    {
        $this->getJson('/api/v1/marketplace/stats')->assertStatus(200);
    }

    public function test_featured_sans_auth_accessible(): void
    {
        $this->getJson('/api/v1/marketplace/featured')->assertStatus(200);
    }
}
