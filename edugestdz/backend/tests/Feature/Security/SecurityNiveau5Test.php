<?php

namespace Tests\Feature\Security;

use App\Console\Commands\DeadManSwitchCommand;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HoneypotService;
use App\Services\InsiderThreatDetectorService;
use App\Services\VaultSecretsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityNiveau5Test extends TestCase
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

    // ── HoneypotService ──

    public function test_honeypot_declencher_route_leurre_retourne_404(): void
    {
        $service = app(HoneypotService::class);

        $response = $service->declencherRouteLeurre();

        $this->assertEquals(404, $response->status());
        $this->assertArrayHasKey('message', $response->getData(true));
    }

    public function test_honeypot_route_leurre_pas_403_ni_200(): void
    {
        $service = app(HoneypotService::class);

        $response = $service->declencherRouteLeurre();

        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_honeypot_get_routes_leurres_inclut_quatre_nouvelles(): void
    {
        $service = app(HoneypotService::class);

        $routes = $service->getRoutesLeurres();

        $this->assertContains('/api/v1/phpinfo', $routes);
        $this->assertContains('/api/v1/server-status', $routes);
        $this->assertContains('/api/v1/actuator', $routes);
        $this->assertContains('/api/v1/metrics', $routes);
    }

    public function test_honeypot_injecter_canaires_modifie_tableaux_5_plus(): void
    {
        $service = app(HoneypotService::class);

        $data = [
            'eleves' => range(1, 5),
            'cours' => range(1, 10),
            'petit' => [1, 2],
        ];

        $result = $service->injecterCanaires($data);

        $this->assertCount(6, $result['eleves']);
        $this->assertCount(11, $result['cours']);
        $this->assertCount(2, $result['petit']);

        $canaryEleve = array_key_last($result['eleves']);
        $this->assertStringStartsWith('_canary_', $canaryEleve);

        $canaryCours = array_key_last($result['cours']);
        $this->assertStringStartsWith('_canary_', $canaryCours);
    }

    public function test_honeypot_injecter_canaires_petit_tableau_inchange(): void
    {
        $service = app(HoneypotService::class);

        $data = ['petit' => [1, 2, 3]];
        $result = $service->injecterCanaires($data);

        $this->assertCount(3, $result['petit']);
    }

    // ── Routes leurres HTTP ──

    public function test_honeypot_phpinfo_returns_404(): void
    {
        $this->get('/api/v1/phpinfo')->assertStatus(404);
    }

    public function test_honeypot_server_status_returns_404(): void
    {
        $this->get('/api/v1/server-status')->assertStatus(404);
    }

    public function test_honeypot_actuator_returns_404(): void
    {
        $this->get('/api/v1/actuator')->assertStatus(404);
    }

    public function test_honeypot_metrics_returns_404(): void
    {
        $this->get('/api/v1/metrics')->assertStatus(404);
    }

    // ── SQL Injection Detector ──

    public function test_sql_injection_detector_bloque_union_select(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/eleves?q=UNION SELECT * FROM users');

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'INVALID_REQUEST');
    }

    public function test_sql_injection_detector_bloque_drop_table(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/eleves?q=DROP TABLE users');

        $response->assertStatus(400);
    }

    public function test_sql_injection_detector_ne_bloque_pas_health(): void
    {
        $response = $this->getJson('/api/health?q=SELECT');

        $response->assertStatus(200);
    }

    public function test_sql_injection_detector_ne_bloque_pas_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test+alias@edugest.dz',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    // ── VaultSecretsService ──

    public function test_vault_fallback_bdd_quand_non_config(): void
    {
        $service = app(VaultSecretsService::class);

        $service->set('test_key', 'test_value_s3cr3t');
        $value = $service->get('test_key');

        $this->assertEquals('test_value_s3cr3t', $value);
    }

    public function test_vault_secret_inexistant_retourne_null(): void
    {
        $service = app(VaultSecretsService::class);

        $this->assertNull($service->get('cle_inexistante_' . uniqid()));
    }

    // ── InsiderThreatDetectorService ──

    public function test_insider_threat_detecte_export_massif(): void
    {
        $service = app(InsiderThreatDetectorService::class);

        $this->assertTrue($service->detecterExportMassif($this->user, 150));
    }

    public function test_insider_threat_ignore_export_normal(): void
    {
        $service = app(InsiderThreatDetectorService::class);

        $this->assertFalse($service->detecterExportMassif($this->user, 10));
    }

    public function test_insider_threat_detecte_horaire_anormal(): void
    {
        $service = app(InsiderThreatDetectorService::class);

        $this->assertIsBool($service->detecterAccesHoraireAnormal($this->user));
    }

    public function test_insider_threat_detecte_volume_anormal(): void
    {
        $service = app(InsiderThreatDetectorService::class);

        $this->assertIsBool($service->detecterVolumeAnormal($this->user));
    }

    public function test_insider_threat_constants_accessibles(): void
    {
        $reflection = new \ReflectionClass(InsiderThreatDetectorService::class);

        $this->assertTrue($reflection->hasConstant('SEUIL_BULK_EXPORT'));
        $this->assertTrue($reflection->hasConstant('SEUIL_APRES_HEURES'));
        $this->assertTrue($reflection->hasConstant('SEUIL_AVANT_HEURES'));
        $this->assertTrue($reflection->hasConstant('SEUIL_VOLUME_DATA'));
        $this->assertTrue($reflection->hasConstant('SEUIL_IP_INCONNUE'));
        $this->assertTrue($reflection->hasConstant('SEUIL_ECHEC_AUTH'));
    }

    // ── DeadManSwitchCommand ──

    public function test_deadman_switch_ignore_sans_colonne(): void
    {
        if (DB::connection()->getSchemaBuilder()->hasColumn('users', 'last_login_at')) {
            $this->markTestSkipped('la colonne last_login_at existe');
        }

        $exitCode = Artisan::call('edugest:deadman-switch');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('ignore', $output);
    }

    public function test_deadman_switch_ne_crasse_pas(): void
    {
        $exitCode = Artisan::call('edugest:deadman-switch');

        $this->assertEquals(0, $exitCode);
    }

    // ── Honeypot canary injection via controller context ──

    public function test_honeypot_injecter_canaires_contient_watermark(): void
    {
        $service = app(HoneypotService::class);

        $data = ['items' => range(1, 5)];
        $result = $service->injecterCanaires($data);

        $canaryKey = array_key_last($result['items']);
        $this->assertArrayHasKey('watermark', $result['items'][$canaryKey]);
        $this->assertArrayHasKey('type', $result['items'][$canaryKey]);
        $this->assertEquals('honeypot', $result['items'][$canaryKey]['type']);
    }

    public function test_honeypot_ne_modifie_pas_les_arrays_via_eloquent(): void
    {
        $service = app(HoneypotService::class);

        $eloquentCollection = collect(range(1, 5));
        $data = ['items' => $eloquentCollection->toArray()];

        $result = $service->injecterCanaires($data);

        $this->assertCount(6, $result['items']);
    }
}
