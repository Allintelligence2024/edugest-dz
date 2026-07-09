<?php

namespace Tests\Feature\Security;

use App\Models\AuditChain;
use App\Models\KillSwitchVote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditChainService;
use App\Services\KillSwitchService;
use App\Services\AsymmetricCryptoService;
use App\Services\SiemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityNiveau6Test extends TestCase
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

    protected function tearDown(): void
    {
        Cache::forget('kill_switch:active');
        parent::tearDown();
    }

    // ── Audit Chain ──

    public function test_audit_chain_genesis_block_exists(): void
    {
        $this->assertDatabaseHas('audit_chain', [
            'bloc_numero' => 0,
            'previous_hash' => str_repeat('0', 64),
        ]);
    }

    public function test_audit_chain_enregistrer_dans_transaction(): void
    {
        $service = app(AuditChainService::class);

        $bloc = $service->enregistrer('USER_LOGIN', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ], $this->user->id);

        $this->assertDatabaseHas('audit_chain', [
            'id' => $bloc->id,
            'bloc_numero' => 1,
        ]);

        $this->assertEquals(64, strlen($bloc->data_hash));
        $this->assertEquals(64, strlen($bloc->previous_hash));
    }

    public function test_audit_chain_blocs_chaines(): void
    {
        $service = app(AuditChainService::class);

        $bloc1 = $service->enregistrer('EVENT_A', ['data' => 'aaa']);
        $bloc2 = $service->enregistrer('EVENT_B', ['data' => 'bbb']);

        $this->assertEquals($bloc1->data_hash, $bloc2->previous_hash);
    }

    public function test_audit_chain_verifier_integrite_complete(): void
    {
        $service = app(AuditChainService::class);

        $service->enregistrer('TEST', ['msg' => 'hello']);
        $service->enregistrer('TEST2', ['msg' => 'world']);

        $result = $service->verifierIntegriteComplete();

        $this->assertTrue($result['valide']);
        $this->assertGreaterThanOrEqual(3, $result['total']);
        $this->assertEmpty($result['invalides']);
    }

    public function test_audit_chain_detecte_payload_modifie(): void
    {
        $service = app(AuditChainService::class);
        $bloc = $service->enregistrer('TEST', ['msg' => 'original']);

        AuditChain::where('id', $bloc->id)->update([
            'payload' => json_encode(['event' => 'TEST', 'msg' => 'modifie']),
        ]);

        $result = $service->verifierIntegriteComplete();
        $this->assertFalse($result['valide']);
    }

    public function test_audit_chain_observer_excludes_sensitive_fields(): void
    {
        $service = app(AuditChainService::class);

        $bloc = $service->enregistrer('USER_CREATED', [
            'email' => 'test@test.com',
            'password' => 'supersecret123',
            'token' => 'abc123',
            'name' => 'John',
        ]);

        $payload = $bloc->payload;
        $this->assertEquals('[REDACTED]', $payload['password']);
        $this->assertEquals('[REDACTED]', $payload['token']);
        $this->assertEquals('John', $payload['name']);
        $this->assertEquals('test@test.com', $payload['email']);
    }

    // ── Kill Switch ──

    public function test_kill_switch_initier_vote(): void
    {
        $service = app(KillSwitchService::class);

        $vote = $service->initierVote($this->user, 'shutdown');

        $this->assertDatabaseHas('kill_switch_votes', [
            'id' => $vote->id,
            'action' => 'shutdown',
            'status' => 'pending',
        ]);
    }

    public function test_kill_switch_vote_expire_apres_fenetre(): void
    {
        $service = app(KillSwitchService::class);

        $vote = $service->initierVote($this->user, 'lockdown');
        $vote->update(['expires_at' => now()->subMinute()]);

        $this->assertTrue($vote->refresh()->estExpire());
    }

    public function test_kill_switch_vote_necessite_deux_admins(): void
    {
        $service = app(KillSwitchService::class);

        $vote = $service->initierVote($this->user, 'emergency');

        $result = $service->approuverVote($this->user, $vote->id);
        $this->assertNull($result);
    }

    public function test_kill_switch_second_admin_approuve(): void
    {
        $service = app(KillSwitchService::class);

        $vote = $service->initierVote($this->user, 'lockdown');

        $admin2 = User::factory()->create([
            'role_id' => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $service->approuverVote($admin2, $vote->id);
        $this->assertNotNull($result);
        $this->assertEquals('approved', $result->status);
    }

    public function test_kill_switch_middleware_returns_503_when_active(): void
    {
        Cache::put('kill_switch:active', true, 60);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/eleves');

        Cache::forget('kill_switch:active');

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
    }

    public function test_kill_switch_middleware_excludes_health(): void
    {
        Cache::put('kill_switch:active', true, 60);

        $response = $this->getJson('/api/health');

        Cache::forget('kill_switch:active');

        $response->assertStatus(200);
    }

    public function test_kill_switch_persiste_en_bdd(): void
    {
        $ks = app(\App\Services\KillSwitchService::class);

        Cache::forget('kill_switch:active');

        $this->assertFalse($ks->estActif());
    }

    // ── Asymmetric Crypto (anciennement "Post-Quantum" — corrigé audit Juillet 2026) ──

    public function test_asymmetric_crypto_sign_and_verify(): void
    {
        $service = app(AsymmetricCryptoService::class);

        $data = 'donnees critiques à signer';
        $signature = $service->signer($data);

        $this->assertTrue($service->verifier($data, $signature));
        $this->assertFalse($service->verifier($data . 'modifie', $signature));
    }

    public function test_asymmetric_crypto_public_key_accessible(): void
    {
        $service = app(AsymmetricCryptoService::class);

        $this->assertNotEmpty($service->getPublicKey());
    }

    public function test_asymmetric_crypto_honnete_resistant_quantique(): void
    {
        $service = app(AsymmetricCryptoService::class);
        $statut = $service->niveauSecuriteReel();

        $this->assertArrayHasKey('algorithme', $statut);
        $this->assertArrayHasKey('resistant_quantique', $statut);
        $this->assertArrayHasKey('resistant_classique', $statut);

        // HONNÊTETÉ : Ed25519 n'est PAS post-quantique (correction audit externe)
        $this->assertFalse($statut['resistant_quantique'],
            'Ed25519 n\'est pas post-quantique (cassable par algorithme de Shor)');

        // Mais excellent contre les attaques classiques
        $this->assertTrue($statut['resistant_classique']);

        $this->assertContains($statut['algorithme'],
            ['Ed25519', 'RSA-4096', 'HMAC-SHA512-fallback']);
    }

    // ── SIEM Service ──

    public function test_siem_evalue_regle_avec_cache(): void
    {
        $service = app(SiemService::class);
        $request = \Illuminate\Http\Request::create('/api/v1/eleves', 'GET');

        $result1 = $service->evaluerRegle('horaire_anormal', $request, $this->user);
        $result2 = $service->evaluerRegle('horaire_anormal', $request, $this->user);

        $this->assertEquals($result1, $result2);
    }

    public function test_siem_detecte_tentative_intrusion(): void
    {
        $service = app(SiemService::class);
        $request = \Illuminate\Http\Request::create('/api/v1/phpinfo', 'GET');

        $result = $service->evaluerRegle('tentative_intrusion', $request, null);

        $this->assertTrue($result['alerte']);
        $this->assertEquals(8, $result['severite']);
    }

    public function test_siem_regle_inconnue_ne_declenche_pas(): void
    {
        $service = app(SiemService::class);
        $request = \Illuminate\Http\Request::create('/api/test', 'GET');

        $result = $service->evaluerRegle('regle_inexistante', $request, null);

        $this->assertArrayNotHasKey('alerte', $result);
    }

    // ── Supply Chain Verifier ──

    public function test_supply_chain_verifier_command_runs(): void
    {
        $exitCode = Artisan::call('edugest:supply-chain-verify');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('SupplyChainVerifier', $output);
    }

    public function test_supply_chain_stores_lock_hash(): void
    {
        Artisan::call('edugest:supply-chain-verify');

        $this->assertNotEmpty(Cache::get('supply_chain:lock_hash'));
    }
}
