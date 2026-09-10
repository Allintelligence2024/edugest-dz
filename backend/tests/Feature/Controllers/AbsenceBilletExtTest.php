<?php
namespace Tests\Feature\Controllers;

use App\Models\{AbsenceJournaliere, Eleve, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceBilletExtTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_marquer_present(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/absences/' . $eleve->id, [
                'statut' => 'present',
            ])
            ->assertStatus(200);
    }

    public function test_marquer_retard(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/absences/' . $eleve->id, [
                'statut' => 'retard',
                'heure_arrivee' => '09:15',
            ])
            ->assertStatus(200);
    }

    public function test_statut_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/absences/' . $eleve->id, [
                'statut' => 'inconnu',
            ])
            ->assertStatus(422);
    }

    public function test_justifier_absence(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $absence = AbsenceJournaliere::create([
            'tenant_id' => $this->tenant->id,
            'eleve_id' => $eleve->id,
            'date_absence' => today()->format('Y-m-d'),
            'statut' => 'absent',
            'signale_par' => 'manuel',
        ]);

        $this->withToken($this->token)
            ->putJson('/api/v1/absences/' . $absence->id . '/justifier', [
                'motif' => 'Certificat médical',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.justificatif.statut', 'en_attente');
    }

    public function test_lister_absences_avec_filtre_date(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/absences?date=' . today()->format('Y-m-d'))
            ->assertStatus(200);
    }

    public function test_emettre_billet_retard(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type' => 'retard',
                'heure' => '08:47',
                'motif' => 'Retard habituel',
            ])
            ->assertStatus(201);
    }

    public function test_emettre_billet_sortie_autorisee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type' => 'sortie_autorisee',
                'heure' => '14:30',
                'motif' => 'Sortie anticipée autorisée',
            ])
            ->assertStatus(201);
    }

    public function test_type_billet_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type' => 'punition',
            ])
            ->assertStatus(422);
    }

    public function test_lister_billets(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/billets')
            ->assertStatus(200);
    }
}
