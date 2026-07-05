<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Eleve;
use App\Models\SignalementComportement;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SignalementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $enseignant;
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $roleAdmin      = Role::factory()->create(['nom' => 'admin']);
        $roleEnseignant = Role::factory()->create(['nom' => 'enseignant']);
        $roleParent     = Role::factory()->create(['nom' => 'parent']);
        $this->admin      = User::factory()->create(['role_id' => $roleAdmin->id,      'tenant_id' => $this->tenant->id]);
        $this->enseignant = User::factory()->create(['role_id' => $roleEnseignant->id, 'tenant_id' => $this->tenant->id]);
        $this->parent     = User::factory()->create(['role_id' => $roleParent->id,     'tenant_id' => $this->tenant->id]);
    }

    public function test_enseignant_peut_signaler_comportement(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'perturbation',
                'gravite'       => 'normale',
                'description'   => 'L\'élève a perturbé le cours de mathématiques.',
                'lieu'          => 'Salle 12',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.type', 'perturbation');
    }

    public function test_admin_peut_signaler(): void
    {
        $eleve = Eleve::factory()->create();

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'félicitation',
                'gravite'       => 'info',
                'description'   => 'Excellent comportement toute la semaine.',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_type_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'insulte_prof',
                'gravite'       => 'grave',
                'description'   => 'test',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(422);
    }

    public function test_date_future_echoue(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'perturbation',
                'gravite'       => 'normale',
                'description'   => 'test',
                'date_incident' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertStatus(422);
    }

    public function test_voir_signalements_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/signalements/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_lister_tous_signalements_admin(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/signalements')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'stats']);
    }

    public function test_traiter_signalement(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $sig   = SignalementComportement::create([
            'tenant_id'     => $this->tenant->id,
            'eleve_id'      => $eleve->id,
            'signale_par'   => $this->enseignant->id,
            'role_auteur'   => 'enseignant',
            'type'          => 'perturbation',
            'gravite'       => 'normale',
            'description'   => 'Test',
            'date_incident' => today()->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/signalements/{$sig->id}/traiter", [
                'suite_donnee' => 'Avertissement verbal donné à l\'élève.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.traite', true);
    }

    public function test_parent_voit_signalements_enfant(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/signalements/parent/mon-enfant')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_notifications_parent_liste(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/notifications/parent')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'non_lues']);
    }

    public function test_marquer_notification_lue(): void
    {
        $notif = \App\Models\NotificationParent::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $this->parent->id,
            'type'      => 'note',
            'titre'     => 'Test',
            'corps'     => 'Test corps',
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/notifications/parent/{$notif->id}/lire")
            ->assertStatus(200);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->postJson('/api/v1/signalements', [])->assertStatus(401);
        $this->getJson('/api/v1/notifications/parent')->assertStatus(401);
    }
}
