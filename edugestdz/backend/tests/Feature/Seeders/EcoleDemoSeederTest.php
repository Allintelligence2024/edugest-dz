<?php

namespace Tests\Feature\Seeders;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Database\Seeders\EcoleDemoSeeder;

class EcoleDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): void
    {
        $this->artisan('db:seed', ['--class' => EcoleDemoSeeder::class]);
    }

    /** @test */
    public function seed_creates_tenant(): void
    {
        $this->runSeeder();

        $tenant = DB::table('tenants')->where('slug', 'ecole-demo')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('École Demo EduGest', $tenant->nom_etablissement);
        $this->assertEquals('actif', $tenant->statut);
    }

    /** @test */
    public function seed_creates_matieres(): void
    {
        $this->runSeeder();

        $count = DB::table('matieres')
            ->where('tenant_id', DB::table('tenants')->where('slug', 'ecole-demo')->value('id'))
            ->count();
        $this->assertGreaterThanOrEqual(9, $count);
    }

    /** @test */
    public function seed_creates_eleves(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $count = DB::table('eleves')->where('tenant_id', $tenantId)->count();
        $this->assertEquals(20, $count);

        $mohamed = DB::table('eleves')
            ->where('tenant_id', $tenantId)
            ->where('prenom', 'Mohamed')
            ->first();
        $this->assertNotNull($mohamed);
        $this->assertStringStartsWith('ECO-', $mohamed->numero_inscription);
    }

    /** @test */
    public function seed_creates_parents_and_pivot(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $parentsCount = DB::table('parents')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(10, $parentsCount);

        $pivotCount = DB::table('eleve_parent')->count();
        $this->assertGreaterThanOrEqual(5, $pivotCount);
    }

    /** @test */
    public function seed_creates_inscriptions(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $count = DB::table('inscriptions')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(10, $count);

        $valides = DB::table('inscriptions')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'validée')
            ->count();
        $this->assertGreaterThan(0, $valides);
    }

    /** @test */
    public function seed_creates_factures_and_paiements(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $facturesCount = DB::table('factures')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(5, $facturesCount);

        $paiementsCount = DB::table('paiements')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(0, $paiementsCount);

        $facture = DB::table('factures')->where('tenant_id', $tenantId)->first();
        $this->assertNotEmpty($facture->numero_facture);
        $this->assertGreaterThan(0, $facture->total_ttc);
    }

    /** @test */
    public function seed_creates_diagnostics(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $count = DB::table('diagnostics_eleves')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(10, $count);

        $diag = DB::table('diagnostics_eleves')->where('tenant_id', $tenantId)->first();
        $this->assertNotEmpty($diag->niveau_global);
        $this->assertGreaterThanOrEqual(0, $diag->score_risque);
    }

    /** @test */
    public function seed_is_idempotent(): void
    {
        $this->runSeeder();

        $tenantId = DB::table('tenants')->where('slug', 'ecole-demo')->value('id');
        $eleves1 = DB::table('eleves')->where('tenant_id', $tenantId)->count();
        $parents1 = DB::table('parents')->where('tenant_id', $tenantId)->count();
        $factures1 = DB::table('factures')->where('tenant_id', $tenantId)->count();
        $diags1 = DB::table('diagnostics_eleves')->where('tenant_id', $tenantId)->count();

        $this->runSeeder();

        $this->assertEquals($eleves1, DB::table('eleves')->where('tenant_id', $tenantId)->count());
        $this->assertEquals($parents1, DB::table('parents')->where('tenant_id', $tenantId)->count());
        $this->assertEquals($factures1, DB::table('factures')->where('tenant_id', $tenantId)->count());
        $this->assertEquals($diags1, DB::table('diagnostics_eleves')->where('tenant_id', $tenantId)->count());
    }
}
