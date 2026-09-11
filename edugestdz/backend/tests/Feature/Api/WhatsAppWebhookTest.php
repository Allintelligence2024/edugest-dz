<?php
namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    public function test_verify_with_correct_token_returns_challenge(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'edugest_verify',
            'hub_challenge'    => '123456789',
        ]));

        $response->assertStatus(200);
        $response->assertSee('123456789');
    }

    public function test_verify_with_wrong_token_returns_403(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge'    => '123456789',
        ]));

        $response->assertStatus(403);
    }

    public function test_verify_without_challenge_returns_403(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'edugest_verify',
        ]));

        $response->assertStatus(403);
    }

    public function test_handle_valid_payload_returns_200(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id'           => 'wamid.test123',
                                        'status'       => 'sent',
                                        'timestamp'    => '1718000000',
                                        'recipient_id' => '213555123456',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_handle_empty_payload_returns_200(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/webhook', []);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_handle_invalid_payload_returns_200(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/webhook', [
            'some_random' => 'data',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
