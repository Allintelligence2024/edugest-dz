<?php

namespace Tests\Feature\Api;

use App\Models\{Seance, Cours, Groupe, Matiere, Enseignant, Salle, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeanceTest extends TestCase
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

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_demarrer_seance(): void
    {
        $cours = Cours::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        $seance = Seance::factory()->create([
            'cours_id'   => $cours->id,
            'tenant_id'  => $this->tenant->id,
            'statut'     => 'planifiée',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/seances/{$seance->id}/demarrer")
            ->assertStatus(200);

        $this->assertEquals('en_cours', $seance->fresh()->statut);
    }

    public function test_terminer_seance(): void
    {
        $cours = Cours::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        $seance = Seance::factory()->create([
            'cours_id'  => $cours->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'en_cours',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/seances/{$seance->id}/terminer")
            ->assertStatus(200);

        $this->assertEquals('terminée', $seance->fresh()->statut);
    }

    public function test_annuler_seance(): void
    {
        $cours = Cours::factory()->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);
        $seance = Seance::factory()->create([
            'cours_id'  => $cours->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'planifiée',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/seances/{$seance->id}/annuler")
            ->assertStatus(200);

        $this->assertEquals('annulée', $seance->fresh()->statut);
    }

    public function test_reporter_seance(): void
    {
        $seance = Seance::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'statut'      => 'planifiée',
        ]);

        $nouvelleDate = now()->addDays(7)->toDateString();

        $this->withToken($this->token)
            ->postJson("/api/v1/seances/{$seance->id}/reporter", [
                'date_seance' => $nouvelleDate,
                'heure_debut' => '10:00',
                'heure_fin'   => '12:00',
            ])
            ->assertStatus(200);

        $seance->refresh();
        $this->assertEquals($nouvelleDate, $seance->date_seance->toDateString());
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $seance = Seance::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/seances/{$seance->id}/demarrer")
            ->assertStatus(404);
    }
}
