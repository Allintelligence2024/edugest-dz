<?php
namespace Tests\Feature\Api;

use App\Models\{DeviceToken, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
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

        $this->token = JWTAuth::fromUser($this->user);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_register_device_token(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/v1/device-tokens', [
                 'token'    => 'fcm-token-123',
                 'platform' => 'android',
             ])
             ->assertStatus(201)
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id'  => $this->user->id,
            'token'    => 'fcm-token-123',
            'platform' => 'android',
        ]);
    }

    public function test_register_duplicate_token(): void
    {
        DeviceToken::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'token'     => 'fcm-token-123',
            'platform'  => 'android',
        ]);

        $this->withToken($this->token)
             ->postJson('/api/v1/device-tokens', [
                 'token'    => 'fcm-token-123',
                 'platform' => 'ios',
             ])
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'token'    => 'fcm-token-123',
            'platform' => 'ios',
        ]);
    }

    public function test_unregister_device_token(): void
    {
        DeviceToken::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'token'     => 'fcm-token-123',
        ]);

        $this->withToken($this->token)
             ->deleteJson('/api/v1/device-tokens', [
                 'token' => 'fcm-token-123',
             ])
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-token-123',
        ]);
    }

    public function test_list_device_tokens(): void
    {
        DeviceToken::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
        ]);

        $this->withToken($this->token)
             ->getJson('/api/v1/device-tokens')
             ->assertStatus(200)
             ->assertJsonCount(2, 'data');
    }

    public function test_isolation_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['statut' => 'actif']);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role_id'   => Role::factory()->create(['nom' => 'admin'])->id,
        ]);

        DeviceToken::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id'   => $otherUser->id,
            'token'     => 'other-tenant-token',
        ]);

        $this->withToken($this->token)
             ->getJson('/api/v1/device-tokens')
             ->assertStatus(200)
             ->assertJsonCount(0, 'data');
    }
}
