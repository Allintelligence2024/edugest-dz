<?php

namespace Tests\Feature\Pilote;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PILOTE-30OCT — fix 1 (rôle eleve en base) + fix 2 (verrouillage tenant pilote).
 */
class RoleEleveEtTenantPiloteTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cree_role_eleve_lecture_seule(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('nom', 'eleve')->firstOrFail();
        $this->assertTrue($role->is_system);

        $noms = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $role->id)
            ->pluck('permissions.nom')
            ->all();
        sort($noms);

        $this->assertSame([
            'bulletins.consulter_pdf',
            'bulletins.lister',
            'evaluations.lister',
            'matieres.lister',
            'planning.consulter',
        ], $noms);
    }

    public function test_configurer_pilote_verrouille_core_uniquement(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);

        // Avant : fail-open, tout actif sans ligne BDD.
        $this->assertTrue(TenantModule::estActif($tenant->id, 'transport'));

        $this->assertSame(0, Artisan::call('tenant:configurer-pilote', ['tenant' => $tenant->id]));

        // 14 modules optionnels désactivés, core intact (aucune ligne, obligatoire).
        $this->assertSame(14, TenantModule::where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, TenantModule::where('tenant_id', $tenant->id)->where('actif', true)->count());

        foreach (['transport', 'cantine', 'stock', 'budget', 'personnel', 'entretien', 'surveillance', 'lms', 'marketplace', 'examens', 'diagnostic', 'billets', 'pointage', 'bibliotheque'] as $key) {
            $this->assertFalse(TenantModule::estActif($tenant->id, $key), "module {$key} devrait être inactif");
        }
        $this->assertTrue(TenantModule::estActif($tenant->id, 'core'));

        // Idempotent : second passage sans doublon.
        $this->assertSame(0, Artisan::call('tenant:configurer-pilote', ['tenant' => $tenant->id]));
        $this->assertSame(14, TenantModule::where('tenant_id', $tenant->id)->count());

        // Preuve HTTP : /modules/actifs ne renvoie plus que core.
        config(['tenant.current_id' => $tenant->id]);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/modules/actifs')
            ->assertStatus(200)
            ->assertJsonPath('data', ['core']);
    }

    public function test_configurer_pilote_tenant_introuvable(): void
    {
        // UUID valide mais absent de la base.
        $this->assertSame(1, Artisan::call('tenant:configurer-pilote', ['tenant' => '00000000-0000-0000-0000-000000000000']));
        // Chaîne quelconque (faute de frappe opérateur) : erreur propre, pas d'exception SQL.
        $this->assertSame(1, Artisan::call('tenant:configurer-pilote', ['tenant' => 'tenant-inexistant']));
    }

    public function test_configurer_pilote_mode_verifier(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);

        // Non verrouillé → exit 1 et rien en base.
        $this->assertSame(1, Artisan::call('tenant:configurer-pilote', ['tenant' => $tenant->id, '--verifier' => true]));
        $this->assertSame(0, TenantModule::where('tenant_id', $tenant->id)->count());

        Artisan::call('tenant:configurer-pilote', ['tenant' => $tenant->id]);

        // Verrouillé → exit 0.
        $this->assertSame(0, Artisan::call('tenant:configurer-pilote', ['tenant' => $tenant->id, '--verifier' => true]));
    }
}
