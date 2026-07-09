<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithMarketplace(array $overrides = []): string
    {
        $tenantId = Str::uuid()->toString();

        DB::table('tenants')->insert(array_merge([
            'id'                     => $tenantId,
            'slug'                   => 'centre-' . substr($tenantId, 0, 8),
            'nom_etablissement'      => 'Centre Controller Test',
            'description'            => 'Description du centre test',
            'wilaya_id'              => 16,
            'adresse'                => '123 Rue Test',
            'telephone'              => '0555000000',
            'email'                  => 'test.' . substr($tenantId, 0, 8) . '@example.com',
            'statut'                 => 'actif',
            'type_etablissement'     => 'centre',
            'marketplace_featured'   => true,
            'created_at'             => now(),
            'updated_at'             => now(),
        ], $overrides));

        DB::table('tenant_modules')->insert([
            'id'           => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'module_key'   => 'marketplace',
            'actif'        => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return $tenantId;
    }

    public function test_marketplace_recherche_endpoint_returns_paginated_tenants(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Recherche 1']);
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Recherche 2']);

        $response = $this->getJson('/api/v1/marketplace/recherche');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta'    => [
                    'total' => 2,
                ],
            ]);
    }

    public function test_marketplace_featured_endpoint_returns_limited_tenants(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->createTenantWithMarketplace(['nom_etablissement' => "Centre Featured {$i}"]);
        }

        $response = $this->getJson('/api/v1/marketplace/featured');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(6, 'data');
    }

    public function test_marketplace_profil_endpoint_returns_centre_data(): void
    {
        $tenantId = $this->createTenantWithMarketplace();

        $response = $this->getJson("/api/v1/marketplace/centres/{$tenantId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'centre'       => ['id' => $tenantId],
                    'offres'       => [],
                    'note_moyenne' => 0,
                    'nb_avis'      => 0,
                ],
            ]);
    }

    public function test_marketplace_profil_endpoint_includes_offres(): void
    {
        $tenantId = $this->createTenantWithMarketplace();

        $matiereId = (string) Str::uuid();
        DB::table('matieres')->insert([
            'id'         => $matiereId,
            'tenant_id'  => $tenantId,
            'nom_fr'     => 'Physique',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('offres_publiques')->insert([
            'id'              => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'matiere_id'      => $matiereId,
            'type_offre'      => 'cours',
            'type_cours'      => 'particulier',
            'description'     => 'Cours de physique',
            'niveau'          => '2nd',
            'tarif_seance'    => 2000,
            'places_restantes'=> 5,
            'statut'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->getJson("/api/v1/marketplace/centres/{$tenantId}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.offres');
    }

    public function test_marketplace_stats_endpoint_returns_statistics(): void
    {
        $this->createTenantWithMarketplace();

        $response = $this->getJson('/api/v1/marketplace/stats');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'total_centres' => 1,
                ],
            ]);
    }

    public function test_marketplace_profil_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->getJson('/api/v1/marketplace/centres/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Ce centre n\'est pas référencé sur la marketplace.',
            ]);
    }

    public function test_marketplace_recherche_validates_wilaya_range(): void
    {
        $response = $this->getJson('/api/v1/marketplace/recherche?wilaya=99');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['wilaya']);
    }

    public function test_marketplace_recherche_validates_per_page_range(): void
    {
        $response = $this->getJson('/api/v1/marketplace/recherche?per_page=100');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}
