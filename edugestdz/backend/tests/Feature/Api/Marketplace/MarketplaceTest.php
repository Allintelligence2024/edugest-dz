<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithMarketplace(array $overrides = []): string
    {
        $tenantId = DB::table('tenants')->insertGetId(array_merge([
            'nom_etablissement'   => 'Centre Test',
            'description'         => 'Description du centre test',
            'wilaya_id'           => 16,
            'adresse'             => '123 Rue Test',
            'telephone'           => '0555000000',
            'email'               => 'test@example.com',
            'statut'              => 'actif',
            'type_etablissement'  => 'centre',
            'created_at'          => now(),
            'updated_at'          => now(),
        ], $overrides));

        DB::table('tenant_modules')->insert([
            'tenant_id'    => $tenantId,
            'module_key'   => 'marketplace',
            'actif'        => true,
            'created_at'   => now(),
            'updated_at'   => now(),
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
                'data' => [
                    '*' => ['id', 'nom_etablissement', 'wilaya', 'type_etablissement'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_recherche_filters_by_wilaya(): void
    {
        $this->createTenantWithMarketplace(['wilaya_id' => 16]);
        $this->createTenantWithMarketplace(['wilaya_id' => 31]);

        $response = $this->getJson('/api/v1/marketplace/recherche?wilaya=16');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_recherche_filters_by_search_query(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Alpha']);
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Beta']);

        $response = $this->getJson('/api/v1/marketplace/recherche?q=Alpha');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_featured_returns_active_marketplace_tenants(): void
    {
        $this->createTenantWithMarketplace(['nom_etablissement' => 'Centre Featured', 'marketplace_featured' => true]);

        $response = $this->getJson('/api/v1/marketplace/featured');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'nom_etablissement', 'wilaya'],
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_profil_returns_centre_with_offres_and_avis(): void
    {
        $tenantId = $this->createTenantWithMarketplace();

        $matiereId = DB::table('matieres')->insertGetId([
            'nom'       => 'Mathématiques',
            'code'      => 'MATH',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $roleEnseignantId = DB::table('roles')->insertGetId([
            'nom'       => 'enseignant',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $roleEleveId = DB::table('roles')->insertGetId([
            'nom'       => 'eleve',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $enseignantId = DB::table('enseignants')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id'   => DB::table('users')->insertGetId([
                'name'     => 'Enseignant Test',
                'email'    => 'enseignant@test.com',
                'password' => bcrypt('password'),
                'tenant_id'=> $tenantId,
                'role_id'  => $roleEnseignantId,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $eleveId = DB::table('eleves')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id'   => DB::table('users')->insertGetId([
                'name'     => 'Eleve Test',
                'email'    => 'eleve@test.com',
                'password' => bcrypt('password'),
                'tenant_id'=> $tenantId,
                'role_id'  => $roleEleveId,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $offreId = DB::table('offres_publiques')->insertGetId([
            'tenant_id'       => $tenantId,
            'enseignant_id'   => $enseignantId,
            'matiere_id'      => $matiereId,
            'type_offre'      => 'cours',
            'type_cours'      => 'particulier',
            'description'     => 'Cours de maths',
            'niveau'          => '3eme',
            'tarif_seance'    => 1500,
            'places_restantes'=> 10,
            'statut'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $reservationId = DB::table('reservations')->insertGetId([
            'tenant_id'  => $tenantId,
            'offre_id'   => $offreId,
            'eleve_id'   => $eleveId,
            'statut'     => 'terminee',
            'montant'    => 1500,
            'date_debut' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('avis')->insert([
            'tenant_id'       => $tenantId,
            'reservation_id'  => $reservationId,
            'eleve_id'        => $eleveId,
            'enseignant_id'   => $enseignantId,
            'note'            => 5,
            'commentaire'     => 'Excellent',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->getJson("/api/v1/marketplace/profil/{$tenantId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'centre',
                    'offres',
                    'note_moyenne',
                    'nb_avis',
                ],
            ])
            ->assertJsonPath('data.centre.id', $tenantId)
            ->assertJsonPath('data.note_moyenne', 5)
            ->assertJsonPath('data.nb_avis', 1);
    }

    public function test_profil_returns_404_for_inactive_tenant(): void
    {
        $response = $this->getJson('/api/v1/marketplace/profil/non-existent-id');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_profil_returns_404_for_tenant_without_marketplace(): void
    {
        $tenantId = DB::table('tenants')->insertGetId([
            'nom_etablissement'  => 'Centre Sans Module',
            'wilaya_id'          => 16,
            'statut'             => 'actif',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $response = $this->getJson("/api/v1/marketplace/profil/{$tenantId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_stats_returns_marketplace_statistics(): void
    {
        $this->createTenantWithMarketplace();
        $this->createTenantWithMarketplace();

        $response = $this->getJson('/api/v1/marketplace/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_centres',
                    'total_wilayas',
                    'message',
                ],
            ])
            ->assertJsonPath('data.total_centres', 2);
    }
}
