<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugAdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_admin_feedback(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $tenant->id]);

        $roleAdmin = Role::factory()->create(['nom' => 'admin']);
        $roleEns   = Role::factory()->create(['nom' => 'enseignant']);
        $roleEleve = Role::factory()->create(['nom' => 'eleve']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $roleAdmin->id,
        ]);
        $enseignant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $roleEns->id,
        ]);
        $eleveUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $roleEleve->id,
        ]);
        $eleve = Eleve::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id'   => $eleveUser->id,
        ]);

        $tokenAdmin = auth('api')->login($admin);

        // Verify role
        $freshUser = User::find($admin->id);
        $this->assertEquals('admin', $freshUser->role->nom);

        // Test feedbacks
        $response = $this->withToken($tokenAdmin)
            ->getJson('/api/v1/feedbacks-pedagogiques');
        $response->dump();
        $this->assertEquals(200, $response->status());

        // Test signalements
        $response2 = $this->withToken($tokenAdmin)
            ->getJson('/api/v1/signalements-graves');
        $response2->dump();
        $this->assertEquals(200, $response2->status());
    }
}
