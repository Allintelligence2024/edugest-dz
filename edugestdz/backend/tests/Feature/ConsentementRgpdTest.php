<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 6 § 5 — Loi 18-07 : consentement parental.
 *
 * Avant ce sprint, la table consentements_rgpd n'était écrite par aucun
 * code : impossible de prouver le consentement des parents pour les
 * données de leurs enfants mineurs.
 */
class ConsentementRgpdTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $role = Role::firstOrCreate(['nom' => 'admin']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = auth('api')->fromUser($user);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_enregistre_un_consentement_parental(): void
    {
        $eleve  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'eleve_id'          => $eleve->id,
                'parent_id'         => $parent->id,
                'type_consentement' => 'droit_image',
                'accepte'           => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('consentements_rgpd', [
            'tenant_id'         => $this->tenant->id,
            'eleve_id'          => $eleve->id,
            'parent_id'         => $parent->id,
            'type_consentement' => 'droit_image',
            'accepte'           => true,
        ]);
    }

    public function test_refus_de_consentement_conserve_lhistorique(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        // Consentement initial…
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'eleve_id'          => $eleve->id,
                'type_consentement' => 'communication_electronique',
                'accepte'           => true,
            ])->assertStatus(201);

        // …puis retrait : une NOUVELLE ligne, l'ancienne reste (piste d'audit).
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'eleve_id'          => $eleve->id,
                'type_consentement' => 'communication_electronique',
                'accepte'           => false,
            ])->assertStatus(201);

        $this->assertDatabaseHas('consentements_rgpd', ['eleve_id' => $eleve->id, 'accepte' => true]);
        $this->assertDatabaseHas('consentements_rgpd', ['eleve_id' => $eleve->id, 'accepte' => false]);
    }

    public function test_exige_eleve_ou_parent(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'type_consentement' => 'droit_image',
                'accepte'           => true,
            ])
            ->assertStatus(422);
    }

    public function test_type_hors_liste_rejete(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'eleve_id'          => $eleve->id,
                'type_consentement' => 'recolte_organes',
                'accepte'           => true,
            ])
            ->assertStatus(422);
    }

    public function test_parent_dun_autre_tenant_introuvable(): void
    {
        $autreTenant = Tenant::factory()->create();
        $parent      = ParentEleve::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/rgpd/consentements', [
                'parent_id'         => $parent->id,
                'type_consentement' => 'droit_image',
                'accepte'           => true,
            ])
            ->assertStatus(404);
    }

    public function test_acces_refuse_aux_non_direction(): void
    {
        $role  = Role::firstOrCreate(['nom' => 'enseignant']);
        $user  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $token = auth('api')->fromUser($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/rgpd/consentements', [
                'type_consentement' => 'droit_image',
                'accepte'           => true,
            ])
            ->assertStatus(403);
    }

    public function test_liste_les_consentements_par_eleve(): void
    {
        $eleveA = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleveB = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([$eleveA, $eleveB] as $eleve) {
            $this->withHeaders($this->authHeaders())
                ->postJson('/api/v1/rgpd/consentements', [
                    'eleve_id'          => $eleve->id,
                    'type_consentement' => 'sorties_scolaires',
                    'accepte'           => true,
                ])->assertStatus(201);
        }

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/rgpd/consentements?eleve_id={$eleveA->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.eleve_id', $eleveA->id);
    }
}
