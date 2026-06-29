<?php

namespace Tests\Feature\Api;

use App\Models\{User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_audit_logs(): void
    {
        Activity::create([
            'log_name'    => 'default',
            'description' => 'created',
            'subject_type' => 'App\Models\Eleve',
            'causer_id'   => auth()->id(),
            'causer_type' => User::class,
            'properties'  => [],
            'tenant_id'   => $this->tenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_filtre_audit(): void
    {
        Activity::create([
            'log_name'    => 'default',
            'description' => 'updated',
            'subject_type' => 'App\Models\Eleve',
            'causer_id'   => auth()->id(),
            'causer_type' => User::class,
            'properties'  => ['old' => ['nom' => 'X'], 'attributes' => ['nom' => 'Y']],
            'tenant_id'   => $this->tenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/audit-logs?action=updated')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_isolation_tenant_audit(): void
    {
        $autreTenant = Tenant::factory()->create();
        Activity::create([
            'log_name'    => 'default',
            'description' => 'created',
            'subject_type' => 'App\Models\Eleve',
            'causer_id'   => auth()->id(),
            'causer_type' => User::class,
            'properties'  => [],
            'tenant_id'   => $autreTenant->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/audit-logs')
            ->assertJsonPath('meta.total', 0);
    }
}
