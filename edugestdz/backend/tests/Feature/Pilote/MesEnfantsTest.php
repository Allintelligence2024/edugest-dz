<?php

namespace Tests\Feature\Pilote;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PILOTE P1-C2..C5 — GET /parents/mes-enfants : contexte enfant du parent connecté.
 */
class MesEnfantsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $parent;

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
        Cache::flush();
    }

    private function lier(User $user, Eleve $eleve): void
    {
        $profil = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $profil->user_id = $user->id;
        $profil->save();
        $eleve->parents()->attach($profil->id, ['est_principal' => true]);
    }

    private function eleve(): Eleve
    {
        return Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_parent_voit_uniquement_ses_enfants(): void
    {
        $a = $this->eleve();
        $b = $this->eleve();
        $autre = $this->eleve();
        $this->lier($this->parent, $a);
        $this->lier($this->parent, $b);

        $res = $this->actingAs($this->parent, 'api')->getJson('/api/v1/parents/mes-enfants');

        $res->assertOk()->assertJsonPath('success', true);
        $ids = collect($res->json('data'))->pluck('id')->all();
        sort($ids);
        $attendus = [$a->id, $b->id];
        sort($attendus);
        $this->assertSame($attendus, $ids);
        $this->assertNotContains($autre->id, $ids);
    }

    public function test_parent_sans_enfant_recoit_liste_vide(): void
    {
        $res = $this->actingAs($this->parent, 'api')->getJson('/api/v1/parents/mes-enfants');

        $res->assertOk()->assertJsonPath('success', true)->assertJsonPath('data', []);
    }

    public function test_enseignant_refuse_403(): void
    {
        $role = Role::factory()->create(['nom' => 'enseignant']);
        $ens = User::factory()->create([
            'role_id' => $role->id, 'tenant_id' => $this->tenant->id, 'statut' => 'actif',
        ]);

        $this->actingAs($ens, 'api')->getJson('/api/v1/parents/mes-enfants')->assertForbidden();
    }

    public function test_non_authentifie_401(): void
    {
        $this->getJson('/api/v1/parents/mes-enfants')->assertUnauthorized();
    }
}
