<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creer_user_par_defaut(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotNull($user->tenant_id);
        $this->assertEquals('actif', $user->statut);
        $this->assertNull($user->role_id);
    }

    public function test_state_unverified_desactive_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at);
    }

    public function test_adminAvec2fa_associe_role_admin(): void
    {
        $user = User::factory()->adminAvec2fa()->create();

        $this->assertNotNull($user->role_id);
        $this->assertEquals('admin', $user->role->nom);
    }

    public function test_adminAvec2fa_ne_cree_pas_de_role_en_double(): void
    {
        Role::firstOrCreate(['nom' => 'admin'], ['nom' => 'admin', 'description' => 'Admin']);

        $countAvant = Role::where('nom', 'admin')->count();

        User::factory()->adminAvec2fa()->create();
        User::factory()->adminAvec2fa()->create();

        $countApres = Role::where('nom', 'admin')->count();

        $this->assertEquals($countAvant, $countApres, 'Role admin ne doit pas être créé en double');
    }

    public function test_user_a_un_tenant(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->tenant);
        $this->assertEquals($user->tenant_id, $user->tenant->id);
    }
}
