<?php

namespace Tests\Feature\Performance;

use App\Models\{Eleve, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EleveResourcePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_eleves_retourne_data(): void
    {
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/eleves')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_liste_eleves_meta_total(): void
    {
        Eleve::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/eleves')
             ->assertStatus(200)
             ->assertJsonPath('meta.total', 5);
    }

    public function test_liste_eleves_cursor_pagination_structure(): void
    {
        Eleve::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
             ->getJson('/api/v1/eleves')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'success', 'data',
                 'meta' => ['total', 'per_page', 'current_page', 'last_page', 'has_more'],
             ]);
    }

    public function test_liste_eleves_sans_age_dans_la_liste(): void
    {
        Eleve::factory()->create(['tenant_id' => $this->tenant->id, 'date_naissance' => '2008-05-15']);

        $response = $this->withToken($this->token)
                         ->getJson('/api/v1/eleves');

        $response->assertStatus(200);
        $first = $response->json('data')[0];
        $this->assertArrayNotHasKey('age', $first);
        $this->assertArrayNotHasKey('photo_url_full', $first);
    }

    public function test_liste_eleves_contient_nom_complet(): void
    {
        Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nom'       => 'BENALI',
            'prenom'    => 'Ahmed',
        ]);

        $response = $this->withToken($this->token)
                         ->getJson('/api/v1/eleves');

        $response->assertStatus(200);
        $first = $response->json('data')[0];
        $this->assertArrayHasKey('nom_complet', $first);
        $this->assertEquals('BENALI Ahmed', $first['nom_complet']);
    }

    public function test_detail_eleve_inclut_age(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'date_naissance' => '2008-05-15',
        ]);

        $response = $this->withToken($this->token)
                         ->getJson("/api/v1/eleves/{$eleve->id}");

        $response->assertStatus(200);
        $eleveData = $response->json('data.eleve');
        $this->assertArrayHasKey('age', $eleveData);
        $this->assertIsInt($eleveData['age']);
    }

    public function test_detail_eleve_inclut_photo_url_full(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'photo_url'  => 'photos/test.jpg',
        ]);

        $response = $this->withToken($this->token)
                         ->getJson("/api/v1/eleves/{$eleve->id}");

        $response->assertStatus(200);
        $eleveData = $response->json('data.eleve');
        $this->assertArrayHasKey('photo_url_full', $eleveData);
    }

    public function test_detail_eleve_sans_date_naissance_age_null(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $eleve->date_naissance = null;
        $this->assertNull($eleve->age);
    }

    public function test_liste_eleves_has_more_pagination(): void
    {
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
                         ->getJson('/api/v1/eleves?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertTrue($response->json('meta.has_more'));
    }
}
