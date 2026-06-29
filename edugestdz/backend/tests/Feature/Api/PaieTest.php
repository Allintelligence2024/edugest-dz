<?php

namespace Tests\Feature\Api;

use App\Models\{Paie, Enseignant, User, Tenant, Role, Matiere, Groupe, Cours, Seance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PaieTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_paies(): void
    {
        Paie::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/paies')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_calculer_paie(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'type_contrat' => 'vacataire',
            'taux_horaire' => 1500,
            'statut'       => 'actif',
        ]);

        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe  = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $cours   = Cours::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'matiere_id' => $matiere->id,
            'groupe_id'  => $groupe->id,
            'heure_debut' => '09:00',
            'heure_fin'   => '11:00',
            'statut'      => 'actif',
        ]);
        Seance::factory()
            ->count(4)
            ->sequence(
                ['date_seance' => now()->startOfMonth()->addDay(2)],
                ['date_seance' => now()->startOfMonth()->addDay(9)],
                ['date_seance' => now()->startOfMonth()->addDay(16)],
                ['date_seance' => now()->startOfMonth()->addDay(23)],
            )
            ->create([
                'cours_id'  => $cours->id,
                'statut'    => 'terminée',
                'tenant_id' => $this->tenant->id,
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paies/calculer', [
                'enseignant_id' => $enseignant->id,
                'mois'          => now()->month,
                'annee'         => now()->year,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'statut']]);
    }

    public function test_valider_paie(): void
    {
        $paie = Paie::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'calculé',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paies/{$paie->id}/valider")
            ->assertStatus(200);

        $this->assertEquals('validé', $paie->fresh()->statut);
    }

    public function test_payer_paie(): void
    {
        $paie = Paie::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'validé',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paies/{$paie->id}/payer", ['mode_paiement' => 'virement'])
            ->assertStatus(200);

        $this->assertEquals('payé', $paie->fresh()->statut);
        $this->assertNotNull($paie->fresh()->date_paiement);
    }

    public function test_bulletin_paie(): void
    {
        $paie = Paie::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paies/{$paie->id}/bulletin")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $paie = Paie::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paies/{$paie->id}")
            ->assertStatus(404);
    }
}
