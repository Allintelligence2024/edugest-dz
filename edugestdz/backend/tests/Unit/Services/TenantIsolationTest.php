<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Eleve, Groupe, Matiere};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_eleve_tenant_a_visible_dans_tenant_a(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Eleve::factory()->create([
            'tenant_id' => $tenantA->id,
            'nom' => 'Visible',
        ]);
        Eleve::factory()->create([
            'tenant_id' => $tenantB->id,
            'nom' => 'Isolé',
        ]);

        config(['tenant.current_id' => $tenantA->id]);
        $eleves = Eleve::where('tenant_id', $tenantA->id)->get();

        $this->assertCount(1, $eleves);
        $this->assertEquals('Visible', $eleves->first()->nom);
    }

    public function test_tenant_b_ne_voit_pas_donnees_tenant_a(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Eleve::factory()->create(['tenant_id' => $tenantA->id]);
        Eleve::factory()->create(['tenant_id' => $tenantB->id]);

        config(['tenant.current_id' => $tenantB->id]);
        $eleves = Eleve::where('tenant_id', $tenantB->id)->get();

        $this->assertCount(1, $eleves);
    }

    public function test_groupe_scoped_par_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        config(['tenant.current_id' => $tenantA->id]);
        $matiere = Matiere::factory()->create();

        $grpA = Groupe::factory()->create([
            'tenant_id' => $tenantA->id,
            'matiere_id' => $matiere->id,
            'nom' => 'Groupe A',
        ]);
        Groupe::factory()->create([
            'tenant_id' => $tenantB->id,
            'matiere_id' => $matiere->id,
            'nom' => 'Groupe B',
        ]);

        $this->assertCount(1, Groupe::where('tenant_id', $tenantA->id)->get());
        $this->assertEquals('Groupe A', $grpA->fresh()->nom);
    }
}
