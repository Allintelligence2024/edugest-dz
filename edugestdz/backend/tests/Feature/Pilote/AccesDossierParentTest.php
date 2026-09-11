<?php

namespace Tests\Feature\Pilote;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PILOTE-30OCT (P1) — le parent lit le dossier de SES enfants.
 *
 * Contexte : GET /api/v1/eleves/{id}/{notes,presences,bulletins,paiements}
 * étaient derrière $lectureEleves SANS le rôle parent (403 silencieux,
 * écrans mobiles vides car les erreurs sont avalées côté mobile).
 * Le middleware admet désormais `parent`, la filiation restant imposée par
 * verifierPerimetreEleve (PerimetreAccesService). La liste globale
 * GET /api/v1/eleves reste interdite au parent (test verrou ci-dessous).
 */
class AccesDossierParentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $tokenParent;
    private Eleve $enfant;
    private Eleve $autre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $parentUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleParent->id,
        ]);
        $this->tokenParent = auth('api')->login($parentUser);

        $this->enfant = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->autre  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $parentEleve = ParentEleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $parentUser->id,
        ]);
        DB::table('eleve_parent')->insert([
            'eleve_id'      => $this->enfant->id,
            'parent_id'     => $parentEleve->id,
            'est_principal' => true,
        ]);
    }

    public function test_parent_lit_notes_de_son_enfant(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->enfant->id}/notes")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_parent_lit_presences_de_son_enfant(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->enfant->id}/presences")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_parent_lit_bulletins_de_son_enfant(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->enfant->id}/bulletins")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_parent_lit_paiements_de_son_enfant(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->enfant->id}/paiements")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_parent_refuse_sur_enfant_d_un_autre(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->autre->id}/notes")
            ->assertStatus(403);

        $this->withToken($this->tokenParent)
            ->getJson("/api/v1/eleves/{$this->autre->id}/paiements")
            ->assertStatus(403);
    }

    public function test_parent_refuse_toujours_liste_globale(): void
    {
        $this->withToken($this->tokenParent)
            ->getJson('/api/v1/eleves')
            ->assertStatus(403);
    }
}
