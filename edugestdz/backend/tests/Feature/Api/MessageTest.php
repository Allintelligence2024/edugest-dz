<?php

namespace Tests\Feature\Api;

use App\Models\{Conversation, Message, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($this->user);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_conversation(): void
    {
        $destinataire = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => Role::factory()->create()->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/messages/conversations', [
                'sujet'       => 'Test conversation',
                'participants' => [$destinataire->id],
                'message'     => 'Bonjour, test',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_liste_conversations(): void
    {
        $conv = Conversation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'participants' => [$this->user->id],
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/messages/conversations')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_envoyer_message(): void
    {
        $conv = Conversation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'participants' => [$this->user->id],
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/messages/conversations/{$conv->id}", [
                'message' => 'Nouveau message',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_non_participant_forbidden(): void
    {
        $autreUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => Role::factory()->create()->id]);
        $token2 = auth('api')->login($autreUser);

        $conv = Conversation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'participants' => [$this->user->id],
        ]);

        $this->withToken($token2)
            ->getJson("/api/v1/messages/conversations/{$conv->id}")
            ->assertStatus(403);
    }

    public function test_isolation_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $conv = Conversation::factory()->create([
            'tenant_id'   => $autreTenant->id,
            'participants' => [$this->user->id],
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/messages/conversations/{$conv->id}")
            ->assertStatus(404);
    }
}
