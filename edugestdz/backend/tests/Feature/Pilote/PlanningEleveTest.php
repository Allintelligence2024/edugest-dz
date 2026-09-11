<?php

namespace Tests\Feature\Pilote;

use App\Models\Cours;
use App\Models\Eleve;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\ParentEleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PILOTE P1-C5 — GET /planning?eleve_id=… : planning scopé aux inscriptions validées,
 * garde périmètre (403 si hors périmètre).
 */
class PlanningEleveTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $parent;
    private Eleve $enfant;
    private Groupe $groupe;

    protected function setUp(): void
    {
        parent::setUp();

        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $this->parent = User::factory()->create([
            'role_id'   => $roleParent->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->enfant = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $profil = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $profil->user_id = $this->parent->id;
        $profil->save();
        $this->enfant->parents()->attach($profil->id, ['est_principal' => true]);

        $this->groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id]);
        Inscription::create([
            'tenant_id'        => $this->tenant->id,
            'eleve_id'         => $this->enfant->id,
            'groupe_id'        => $this->groupe->id,
            'annee_scolaire'   => '2025-2026',
            'date_inscription' => today()->toDateString(),
            'statut'           => 'validée',
        ]);

        Cache::flush();
    }

    public function test_parent_recoit_planning_groupe_par_jour(): void
    {
        Cours::factory()->create([
            'tenant_id' => $this->tenant->id,
            'groupe_id' => $this->groupe->id,
            'statut'    => 'actif',
        ]);

        $res = $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/planning?eleve_id=' . $this->enfant->id);

        $res->assertOk()->assertJsonPath('success', true);
        $jours = $res->json('data');
        $this->assertNotEmpty($jours);
        $this->assertArrayHasKey('date', $jours[0]);
        $this->assertArrayHasKey('seances', $jours[0]);
    }

    public function test_parent_refuse_enfant_hors_perimetre(): void
    {
        $autre = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/planning?eleve_id=' . $autre->id)
            ->assertForbidden();
    }

    public function test_admin_accede_planning_eleve(): void
    {
        $role = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $role->id, 'tenant_id' => $this->tenant->id, 'statut' => 'actif',
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/planning?eleve_id=' . $this->enfant->id)
            ->assertOk();
    }

    public function test_eleve_id_invalide_422(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/planning?eleve_id=pas-un-uuid')
            ->assertStatus(422);
    }
}
