<?php

namespace Tests\Feature;

use App\Enums\StatutEleve;
use App\Enums\StatutFacture;
use App\Enums\TypeContrat;
use App\Exceptions\TenantException;
use App\Exceptions\ModuleDesactiveException;
use App\Exceptions\PaiementException;
use App\Http\Resources\EleveResource;
use App\Models\{Eleve, Tenant, User, Role, Facture};
use App\Services\HoneypotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);

        $role = Role::factory()->create(['nom' => 'admin']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);

        $this->token = auth('api')->login($this->admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    // ── Enums ─────────────────────────────────────────────────

    public function test_enums_statut_eleve_existent(): void
    {
        $this->assertTrue(defined(StatutEleve::class . '::ACTIF'));
        $this->assertEquals('actif', StatutEleve::ACTIF->value);
        $this->assertEquals('inactif', StatutEleve::INACTIF->value);
        $this->assertEquals('suspendu', StatutEleve::SUSPENDU->value);
        $this->assertEquals('ancien', StatutEleve::ANCIEN->value);
    }

    public function test_enums_statut_facture_existent(): void
    {
        $this->assertEquals('payee', StatutFacture::PAYEE->value);
        $this->assertEquals('impayee', StatutFacture::IMPAYEE->value);
        $this->assertEquals('annulee', StatutFacture::ANNULEE->value);
        $this->assertEquals('en_attente', StatutFacture::EN_ATTENTE->value);
    }

    public function test_enums_type_contrat_existent(): void
    {
        $this->assertEquals('cdi', TypeContrat::CDI->value);
        $this->assertEquals('cdd', TypeContrat::CDD->value);
        $this->assertEquals('prestation', TypeContrat::PRESTATION->value);
        $this->assertEquals('stage', TypeContrat::STAGE->value);
    }

    // ── Exceptions ────────────────────────────────────────────────

    public function test_tenant_exception_render(): void
    {
        $e = new TenantException('Test', 403);
        $response = $e->render();
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Test', $response->getData()->message);
    }

    public function test_module_desactive_exception_render(): void
    {
        $e = new ModuleDesactiveException('Module test');
        $response = $e->render();
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString("n'est pas activé", $response->getData()->message);
    }

    public function test_paiement_exception_render(): void
    {
        $e = new PaiementException('Erreur paiement');
        $response = $e->render();
        $this->assertEquals(422, $response->getStatusCode());
    }

    // ── Contracts ─────────────────────────────────────────────────

    public function test_contracts_interfaces_existent(): void
    {
        $this->assertTrue(interface_exists(\App\Contracts\NotificationServiceInterface::class));
        $this->assertTrue(interface_exists(\App\Contracts\StorageServiceInterface::class));
    }

    // ── API Resources ──────────────────────────────────────────────

    public function test_eleve_resource_expose_appended_attributes(): void
    {
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $resource = new EleveResource($eleve);
        $data = $resource->toArray(Request::create('/'));

        $this->assertArrayHasKey('nom_complet', $data);
        $this->assertArrayNotHasKey('age', $data);
        $this->assertArrayNotHasKey('photo_url_full', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nom', $data);
        $this->assertArrayHasKey('prenom', $data);
    }

    // ── Notifications in-app ──────────────────────────────────────

    public function test_notifications_inapp_retourne_liste(): void
    {
        DB::table('notifications_inapp')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->id,
            'user_id'    => $this->admin->id,
            'type'       => 'test',
            'titre'      => 'Test notification',
            'corps'      => 'Corps du message',
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/notifications/in-app')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data', 'meta'])
            ->assertJsonStructure(['meta' => ['nb_non_lu']]);
    }

    public function test_notification_marquer_lue(): void
    {
        $notifId = (string) Str::uuid();
        DB::table('notifications_inapp')->insert([
            'id'         => $notifId,
            'tenant_id'  => $this->tenant->id,
            'user_id'    => $this->admin->id,
            'type'       => 'test',
            'titre'      => 'À lire',
            'corps'      => 'Corps',
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson("/api/v1/notifications/in-app/{$notifId}/lu")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notifications_inapp', ['id' => $notifId, 'lu' => true]);
    }

    public function test_notification_autre_user_non_marquable(): void
    {
        $autreUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $this->admin->role_id]);
        $notifId   = (string) Str::uuid();

        DB::table('notifications_inapp')->insert([
            'id'         => $notifId,
            'tenant_id'  => $this->tenant->id,
            'user_id'    => $autreUser->id,
            'type'       => 'test',
            'titre'      => 'Pas le bon user',
            'corps'      => 'Corps',
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson("/api/v1/notifications/in-app/{$notifId}/lu")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications_inapp', ['id' => $notifId, 'lu' => false]);
    }

    public function test_notifications_sans_auth_retourne_401(): void
    {
        $this->getJson('/api/v1/notifications/in-app')->assertStatus(401);
    }

    // ── Policies ──────────────────────────────────────────────────

    public function test_policy_eleve_admin_peut_tout_voir(): void
    {
        $policy = new \App\Policies\ElevePolicy();
        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_policy_eleve_parent_ne_peut_pas_creer(): void
    {
        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $parent     = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleParent->id,
        ]);
        $policy = new \App\Policies\ElevePolicy();
        $this->assertFalse($policy->create($parent));
    }

    public function test_policy_facture_admin_peut_exporter(): void
    {
        $policy = new \App\Policies\FacturePolicy();
        $this->assertTrue($policy->exporter($this->admin));
    }

    // ── Remplacement enseignant ───────────────────────────────────

    public function test_seances_orphelines_accessible(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/remplacements/seances-orphelines')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_export_excel_eleves_accessible(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/eleves/export/excel')
            ->assertStatus(200);
    }

    // ── LicenceCheck ──────────────────────────────────────────────

    public function test_tenant_suspendu_bloque_api(): void
    {
        $tenantSusp = Tenant::factory()->create(['statut' => 'suspendu']);
        $role       = Role::factory()->create(['nom' => 'admin']);
        $userSusp   = User::factory()->create([
            'tenant_id' => $tenantSusp->id,
            'role_id'   => $role->id,
        ]);
        $tokenSusp  = auth('api')->login($userSusp);

        $this->withHeaders(['Authorization' => "Bearer {$tokenSusp}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_EXPIRED');
    }

    public function test_tenant_actif_passe_normalement(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
    }

    // ── Honeypot ──────────────────────────────────────────────────

    public function test_honeypot_22_routes(): void
    {
        $honeypot = app(HoneypotService::class);
        $this->assertCount(22, $honeypot->getRoutesLeurres());
    }

    public function test_honeypot_route_leurre_retourne_404(): void
    {
        $this->get('/api/v1/phpinfo')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found.']);
    }

    // ── FormRequests ──────────────────────────────────────────────

    public function test_store_paiement_request_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Requests\StorePaiementRequest::class));
    }

    // ── Honeypot Canaries ─────────────────────────────────────────

    public function test_honeypot_injecte_canaires(): void
    {
        $honeypot = app(HoneypotService::class);
        $data = [
            'eleves' => array_fill(0, 10, ['id' => 1, 'nom' => 'Test']),
        ];
        $result = $honeypot->injecterCanaires($data);
        $hasCanary = false;
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (str_starts_with((string) $subKey, '_canary_')) {
                        $hasCanary = true;
                        break 2;
                    }
                }
            }
        }
        $this->assertTrue($hasCanary, 'Les canaries doivent être injectés dans les tableaux de ≥ 5 éléments');
    }
}
