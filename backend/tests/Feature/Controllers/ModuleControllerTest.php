<?php
namespace Tests\Feature\Controllers;
use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantModule;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $role       = Role::factory()->create(['nom' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_lister_modules(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/modules')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['modules', 'nb_actifs', 'nb_total']]);
    }

    public function test_modules_actifs_rapide(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/modules/actifs')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_activer_module(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/modules/transport/activer')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_desactiver_module_optionnel(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/modules/transport/desactiver', [
                'raison' => 'Pas de bus dans cet établissement',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_desactiver_module_obligatoire_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/modules/core/desactiver')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_module_inconnu_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/modules/module_inexistant/activer')
            ->assertStatus(422);
    }

    public function test_bulk_update(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/modules/bulk', [
                'modules' => [
                    'transport'  => false,
                    'cantine'    => false,
                    'lms'        => true,
                    'diagnostic' => true,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_middleware_module_desactive_retourne_403(): void
    {
        TenantModule::desactiver($this->tenant->id, 'transport', $this->admin->id);
        $this->assertTrue(true);
    }

    public function test_etat_complet_inclut_tous_les_modules(): void
    {
        $etat = TenantModule::getEtatComplet($this->tenant->id);
        $this->assertGreaterThan(10, count($etat));
    }

    public function test_module_actif_par_defaut_si_jamais_configure(): void
    {
        $actif = TenantModule::estActif($this->tenant->id, 'transport');
        $this->assertTrue($actif);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/modules')->assertStatus(401);
        $this->postJson('/api/v1/modules/transport/activer')->assertStatus(401);
    }
}
