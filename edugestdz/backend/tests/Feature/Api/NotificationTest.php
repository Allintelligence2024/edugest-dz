<?php
namespace Tests\Feature\Api;

use App\Models\{Notification, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role         = Role::factory()->create(['nom' => 'admin']);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);

        $this->token = auth('api')->login($this->user);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_notifications(): void
    {
        Notification::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
        ]);

        $this->withToken($this->token)
             ->getJson('/api/v1/notifications')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data', 'non_lu']);
    }

    public function test_envoyer_notification(): void
    {
        $destinataire = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => Role::factory()->create(['nom' => 'secretaire'])->id,
        ]);

        $this->withToken($this->token)
             ->postJson('/api/v1/notifications/envoyer', [
                 'destinataire_id' => $destinataire->id,
                 'titre'           => 'Rappel de paie',
                 'message'         => 'Votre bulletin de paie est disponible.',
                 'type'            => 'info',
             ])
             ->assertStatus(201)
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $destinataire->id,
            'titre'   => 'Rappel de paie',
        ]);
    }

    public function test_marquer_notification_lue(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'est_lu'    => false,
        ]);

        $this->withToken($this->token)
             ->postJson("/api/v1/notifications/{$notif->id}/lu")
             ->assertStatus(200)
             ->assertJson(['success' => true]);

        $notif->refresh();
        $this->assertTrue($notif->est_lu);
    }

    public function test_tout_lire(): void
    {
        Notification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'est_lu'    => false,
        ]);

        $this->withToken($this->token)
             ->postJson('/api/v1/notifications/tout-lire')
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }

    public function test_supprimer_notification(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
        ]);

        $this->withToken($this->token)
             ->deleteJson("/api/v1/notifications/{$notif->id}")
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }
}
