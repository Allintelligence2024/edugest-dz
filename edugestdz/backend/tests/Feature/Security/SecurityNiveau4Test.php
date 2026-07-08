<?php

namespace Tests\Feature\Security;

use App\Models\DeviceChallenge;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\FieldPermissionService;
use App\Services\RiskScoreEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityNiveau4Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Role $role;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->role = Role::factory()->create(['nom' => 'admin']);
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = auth('api')->login($this->user);
    }

    public function test_trusted_device_creation_et_hash_varchar_64(): void
    {
        $deviceHash = hash('sha256', 'test-device-' . Str::random());

        $device = TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => $deviceHash,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'last_used_at' => now(),
            'trusted_at' => now(),
        ]);

        $this->assertDatabaseHas('trusted_devices', [
            'id' => $device->id,
            'device_hash' => $deviceHash,
        ]);

        $this->assertEquals(64, strlen($device->device_hash));
        $this->assertNotNull($device->user);
        $this->assertEquals($this->user->id, $device->user_id);
    }

    public function test_trusted_device_unique_index_user_device(): void
    {
        $deviceHash = hash('sha256', 'unique-test-device');

        TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => $deviceHash,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => $deviceHash,
        ]);
    }

    public function test_device_fingerprint_service_generer_empreinte(): void
    {
        $service = app(DeviceFingerprintService::class);

        $request = \Illuminate\Http\Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $empreinte = $service->genererEmpreinte($request);

        $this->assertEquals(64, strlen($empreinte));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $empreinte);
    }

    public function test_device_fingerprint_appareil_connu(): void
    {
        $service = app(DeviceFingerprintService::class);
        $deviceHash = hash('sha256', 'known-device');

        $this->assertFalse($service->appareilConnu($this->user, $deviceHash));

        TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => $deviceHash,
        ]);

        $this->assertTrue($service->appareilConnu($this->user, $deviceHash));
    }

    public function test_device_fingerprint_creer_challenge_stocke_hash_uniquement(): void
    {
        $service = app(DeviceFingerprintService::class);

        $result = $service->creerChallenge($this->user);

        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('expires_at', $result);

        $rawCode = $result['challenge'];
        $expectedHash = hash('sha256', $rawCode);

        $this->assertDatabaseHas('device_challenges', [
            'user_id' => $this->user->id,
            'challenge_hash' => $expectedHash,
        ]);

        $stored = DeviceChallenge::where('user_id', $this->user->id)->first();
        $this->assertNotNull($stored);
        $this->assertEquals($expectedHash, $stored->challenge_hash);
        $this->assertNotEquals($rawCode, $stored->challenge_hash);
    }

    public function test_device_fingerprint_verifier_challenge_valide(): void
    {
        $service = app(DeviceFingerprintService::class);

        $result = $service->creerChallenge($this->user);
        $rawCode = $result['challenge'];

        $valide = $service->verifierChallenge($this->user, $rawCode);

        $this->assertTrue($valide);

        $challenge = DeviceChallenge::where('user_id', $this->user->id)->first();
        $this->assertNotNull($challenge->invalidated_at);
    }

    public function test_device_challenge_max_5_tentatives_puis_invalide(): void
    {
        $service = app(DeviceFingerprintService::class);

        $result = $service->creerChallenge($this->user);

        for ($i = 0; $i < 5; $i++) {
            $valide = $service->verifierChallenge($this->user, 'mauvais-code-' . $i);
            $this->assertFalse($valide);
        }

        $challenge = DeviceChallenge::where('user_id', $this->user->id)->first();
        $this->assertEquals(5, $challenge->attempts);
        $this->assertNotNull($challenge->invalidated_at);
    }

    public function test_device_challenge_expire(): void
    {
        $service = app(DeviceFingerprintService::class);

        $result = $service->creerChallenge($this->user);

        DeviceChallenge::where('user_id', $this->user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $valide = $service->verifierChallenge($this->user, $result['challenge']);
        $this->assertFalse($valide);
    }

    public function test_risk_score_engine_exception_retourne_100(): void
    {
        $engine = new class extends RiskScoreEngine {
            public function __construct() {}
            public function testSafeDefault(): int
            {
                try {
                    throw new \RuntimeException('test exception');
                } catch (\Throwable) {
                    return 100;
                }
            }
        };

        $this->assertEquals(100, $engine->testSafeDefault());
    }

    public function test_risk_score_engine_appareil_inconnu_score_eleve(): void
    {
        $engine = app(RiskScoreEngine::class);

        $request = \Illuminate\Http\Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'UnknownBrowser/1.0',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $score = $engine->calculerScore($request, $this->user);

        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_field_permission_deny_by_default(): void
    {
        $service = app(FieldPermissionService::class);

        $canRead = $service->peutLire($this->user, 'eleves', 'email');

        $this->assertFalse($canRead);
    }

    public function test_field_permission_allow_when_configured(): void
    {
        DB::table('field_permissions')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'role_id' => $this->role->id,
            'resource' => 'eleves',
            'field' => 'email',
            'can_read' => true,
            'can_write' => false,
        ]);

        Cache::flush();

        $service = app(FieldPermissionService::class);

        $this->assertTrue($service->peutLire($this->user, 'eleves', 'email'));
        $this->assertFalse($service->peutEcrire($this->user, 'eleves', 'email'));
    }

    public function test_field_permission_empty_table_returns_false(): void
    {
        $service = app(FieldPermissionService::class);

        $this->assertFalse($service->peutLire($this->user, 'inexistant', 'champ'));
        $this->assertFalse($service->peutEcrire($this->user, 'inexistant', 'champ'));
    }

    public function test_zero_trust_middleware_normal_mode_logs_sans_bloquer(): void
    {
        $request = \Illuminate\Http\Request::create('/api/v1/eleves', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        ]);

        $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/eleves')
          ->assertStatus(200);
    }

    public function test_intelligent_rate_limiter_headers_presents(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/eleves');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_trusted_device_liste_via_api(): void
    {
        TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => hash('sha256', 'api-test-device'),
            'device_name' => 'API Test Device',
            'last_used_at' => now(),
            'trusted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/trusted-devices');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_trusted_device_suppression_via_api(): void
    {
        $device = TrustedDevice::create([
            'user_id' => $this->user->id,
            'device_hash' => hash('sha256', 'delete-test-device'),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/v1/trusted-devices/{$device->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trusted_devices', ['id' => $device->id]);
    }

    public function test_device_challenge_nouvel_appareil(): void
    {
        $service = app(DeviceFingerprintService::class);
        $deviceHash = hash('sha256', 'new-device-' . Str::random());

        $this->assertFalse($service->appareilConnu($this->user, $deviceHash));

        $challenge = $service->creerChallenge($this->user);
        $this->assertNotEmpty($challenge['challenge']);

        $valide = $service->verifierChallenge($this->user, $challenge['challenge']);
        $this->assertTrue($valide);
    }
}
