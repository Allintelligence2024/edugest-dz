<?php

namespace Tests\Feature\Api;

use App\Models\{User, Tenant, Role, DeviceToken};
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected DeviceToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'parent']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);
        $this->token = DeviceToken::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'token'     => 'fake_device_token_' . uniqid(),
            'platform'  => 'android',
        ]);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_firebase_send_notification(): void
    {
        $service = app(FirebaseService::class);
        $result  = $service->sendNotification(
            [$this->token->token],
            'Test Title',
            'Test Body',
            ['key' => 'value']
        );

        $this->assertIsBool($result);
    }

    public function test_firebase_notify_user(): void
    {
        $service = app(FirebaseService::class);
        $result  = $service->notifyUser($this->user->id, 'Titre', 'Message');

        $this->assertIsBool($result);
    }

    public function test_firebase_send_to_user_legacy(): void
    {
        $service = app(FirebaseService::class);
        $result  = $service->sendToUser($this->user->id, [
            'title' => 'Titre legacy',
            'body'  => 'Message legacy',
        ]);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failure', $result);
    }

    public function test_firebase_send_to_user_no_tokens(): void
    {
        $this->token->delete();

        $service = app(FirebaseService::class);
        $result  = $service->notifyUser($this->user->id, 'Titre', 'Body');

        $this->assertFalse($result);
    }

    public function test_firebase_send_to_multiple(): void
    {
        $service = app(FirebaseService::class);
        $result  = $service->sendToMultiple(
            [$this->token->token],
            ['title' => 'Mass', 'body' => 'Alert'],
        );

        $this->assertArrayHasKey('success', $result);
    }
}
