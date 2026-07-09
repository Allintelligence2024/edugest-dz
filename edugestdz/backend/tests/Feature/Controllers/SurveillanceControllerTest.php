<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\CameraConfig;
use App\Models\AlerteSurveillance;
use App\Models\TenantModule;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SurveillanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        TenantModule::create([
            'tenant_id'  => $this->tenant->id,
            'module_key' => 'surveillance',
            'actif'      => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id'   => $role->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);
    }

    public function test_webhook_dahua_retourne_200_meme_sans_payload(): void
    {
        $this->postJson('/api/v1/surveillance/webhook', [])
            ->assertStatus(200);
    }

    public function test_webhook_dahua_payload_video_motion(): void
    {
        $camera = CameraConfig::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Entrée principale',
            'serial_no'   => 'DAH2026TEST001',
            'type'        => 'entree',
            'actif'       => true,
        ]);

        $payload = [
            'SerialNo'  => 'DAH2026TEST001',
            'IpAddress' => '192.168.1.64',
            'ChannelID' => 1,
            'Events'    => [['Code' => 'VideoMotion', 'Action' => 'Start', 'Index' => 0]],
            'LocaleTime'=> now()->format('Y-m-d H:i:s'),
        ];

        $this->postJson('/api/v1/surveillance/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonPath('received', true)
            ->assertJsonPath('processed', true);

        $this->assertDatabaseHas('alertes_surveillance', [
            'serial_no'   => 'DAH2026TEST001',
            'type_alerte' => 'VideoMotion',
        ]);
    }

    public function test_webhook_serial_inconnu_retourne_200_non_traite(): void
    {
        $this->postJson('/api/v1/surveillance/webhook', [
            'SerialNo'  => 'SERIAL_INCONNU_XXXXX',
            'IpAddress' => '10.0.0.1',
            'Events'    => [['Code' => 'VideoMotion', 'Action' => 'Start']],
        ])
            ->assertStatus(200)
            ->assertJsonPath('processed', false);
    }

    public function test_lister_alertes_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/surveillance/alertes')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['alertes', 'stats']]);
    }

    public function test_lister_cameras_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/surveillance/cameras')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_enregistrer_camera(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/surveillance/cameras', [
                'nom'          => 'Caméra Test',
                'serial_no'    => 'DAH2026NEWCAM',
                'type'         => 'entree',
                'ip_locale'    => '192.168.1.100',
                'localisation' => 'Bâtiment A',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['camera', 'webhook_url', 'instructions']]);
    }

    public function test_serial_duplique_echoue(): void
    {
        CameraConfig::create([
            'tenant_id' => $this->tenant->id, 'nom' => 'Existante',
            'serial_no' => 'DAH2026DUPLIC', 'type' => 'entree', 'actif' => true,
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/surveillance/cameras', [
                'nom'       => 'Autre',
                'serial_no' => 'DAH2026DUPLIC',
                'type'      => 'couloir',
            ])
            ->assertStatus(422);
    }

    public function test_traiter_alerte(): void
    {
        $camera = CameraConfig::create([
            'tenant_id' => $this->tenant->id,
            'nom' => 'Test', 'serial_no' => 'S1', 'type' => 'entree', 'actif' => true,
        ]);
        $alerte = AlerteSurveillance::create([
            'tenant_id'   => $camera->tenant_id,
            'camera_id'   => $camera->id,
            'serial_no'   => 'S1',
            'type_alerte' => 'VideoMotion',
            'niveau'      => 'warning',
            'survenu_le'  => now(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/surveillance/alertes/{$alerte->id}/traiter", [
                'note_admin' => 'Fausse alarme',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.traite', true);
    }

    public function test_webhook_sans_auth_accessible(): void
    {
        $response = $this->postJson('/api/v1/surveillance/webhook', ['test' => true]);
        $this->assertNotEquals(401, $response->status());
    }

    public function test_alertes_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/surveillance/alertes')->assertStatus(401);
    }
}
