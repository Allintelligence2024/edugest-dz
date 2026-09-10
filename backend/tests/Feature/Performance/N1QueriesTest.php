<?php

namespace Tests\Feature\Performance;

use App\Models\{Eleve, Facture, User, Tenant, Role, ParentEleve, DiagnosticEleve, LigneFacture, Paiement};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class N1QueriesTest extends TestCase
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

    public function test_eleve_index_eager_loads_parents(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Benzema',
            'prenom'      => 'Karim',
            'lien'        => 'pere',
            'telephone_1' => '+213555000000',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        $parentQueries = array_filter($queries, fn($q) =>
            str_contains($q['query'], 'parent_eleves') || str_contains($q['query'], 'parent_elev')
        );

        DB::disableQueryLog();

        // With eager loading: 1 query for parents (via IN clause), not N queries
        $this->assertLessThanOrEqual(2, count($parentQueries),
            'N+1 detected on parents: ' . count($parentQueries) . ' queries for parent_eleves');
    }

    public function test_eleve_index_eager_loads_diagnostic(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        DiagnosticEleve::create([
            'tenant_id'       => $this->tenant->id,
            'eleve_id'        => $eleve->id,
            'niveau_global'   => 'normal',
            'score_risque'    => 15.0,
            'moyenne_generale'=> 12.5,
        ]);

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        $diagQueries = array_filter($queries, fn($q) =>
            str_contains($q['query'], 'diagnostics_eleves')
        );

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, count($diagQueries),
            'N+1 detected on diagnosticEleve: ' . count($diagQueries) . ' queries');
    }

    public function test_facture_index_eager_loads_eleve_parents(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Hakimi',
            'prenom'      => 'Achraf',
            'lien'        => 'pere',
            'telephone_1' => '+213555111111',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        Facture::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $eleve->id,
        ]);

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/factures')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        $parentQueries = array_filter($queries, fn($q) =>
            str_contains($q['query'], 'parent_eleves') || str_contains($q['query'], 'parent_elev')
        );

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, count($parentQueries),
            'N+1 detected on eleve.parents: ' . count($parentQueries) . ' queries');
    }

    public function test_facture_index_eager_loads_lignes(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Facture::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $eleve->id,
        ])->each(function ($facture) {
            LigneFacture::create([
                'tenant_id'      => $this->tenant->id,
                'facture_id'     => $facture->id,
                'description'    => 'Cours Maths',
                'prix_unitaire'  => 15000,
                'quantite'       => 1,
                'total'          => 15000,
                'type_ligne'     => 'cours',
            ]);
        });

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/factures')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        $ligneQueries = array_filter($queries, fn($q) =>
            str_contains($q['query'], 'ligne_factures')
        );

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, count($ligneQueries),
            'N+1 detected on lignes: ' . count($ligneQueries) . ' queries');
    }

    public function test_facture_index_eager_loads_paiements(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Facture::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $eleve->id,
        ])->each(function ($facture) {
            Paiement::create([
                'tenant_id'     => $this->tenant->id,
                'facture_id'    => $facture->id,
                'eleve_id'      => $facture->eleve_id,
                'montant'       => 5000,
                'statut'        => 'confirmé',
                'mode_paiement' => 'especes',
                'date_paiement' => now()->toDateString(),
            ]);
        });

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/factures')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        $paiementQueries = array_filter($queries, fn($q) =>
            str_contains($q['query'], 'paiements') && !str_contains($q['query'], 'ligne')
        );

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, count($paiementQueries),
            'N+1 detected on paiements: ' . count($paiementQueries) . ' queries');
    }

    public function test_rate_limiter_returns_429_when_exceeded(): void
    {
        // Array cache doesn't persist between requests in CI — skip if rate limit can't trigger
        if (config('cache.default') === 'array') {
            $this->markTestSkipped('Array cache does not persist counters — rate limit cannot trigger 429');
        }

        // Make 101 requests to exceed the limit
        for ($i = 0; $i < 101; $i++) {
            $this->withToken($this->token)->getJson('/api/v1/eleves');
        }

        $this->withToken($this->token)
            ->getJson('/api/v1/eleves')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    }
}
