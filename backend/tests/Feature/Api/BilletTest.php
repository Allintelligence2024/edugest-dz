<?php

namespace Tests\Feature\Api;

use App\Models\Billet;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BilletTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_billet_retard(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id'    => $eleve->id,
                'type'        => 'retard',
                'heure'       => '08:45',
                'motif'       => 'Embouteillages',
                'date_billet' => today()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Billet de Retard');

        $this->assertDatabaseHas('billets', [
            'eleve_id' => $eleve->id,
            'type'     => 'retard',
        ]);
    }

    public function test_creer_billet_sortie_autorisee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id'       => $eleve->id,
                'type'           => 'sortie_autorisee',
                'heure'          => '14:30',
                'motif'          => 'Rendez-vous médical',
                'parent_prevenu' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Autorisation de Sortie');
    }

    public function test_creer_convocation(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'convocation',
                'motif'    => 'Comportement en classe',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Convocation Parent');
    }

    public function test_liste_billets_filtree_par_tenant(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        Billet::create([
            'tenant_id'  => $this->tenant->id,
            'eleve_id'   => $eleve->id,
            'type'       => 'retard',
            'date_billet'=> today(),
        ]);

        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        Billet::create([
            'tenant_id'  => $autreTenant->id,
            'eleve_id'   => $autreEleve->id,
            'type'       => 'retard',
            'date_billet'=> today(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/billets')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_historique_billets_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        Billet::create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id, 'type' => 'retard',           'date_billet' => today()]);
        Billet::create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id, 'type' => 'sortie_autorisee', 'date_billet' => today()->subDay()]);

        $this->withToken($this->token)
            ->getJson("/api/v1/billets/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.stats.retards', 1)
            ->assertJsonPath('data.stats.sorties', 1)
            ->assertJsonPath('data.stats.total', 2);
    }

    public function test_validation_type_billet(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'type_invalide',
            ])
            ->assertStatus(422);
    }
}
