<?php

namespace Tests\Unit\Policies;

use App\Models\{User, Role};
use App\Policies\FluxInfoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FluxInfoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FluxInfoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FluxInfoPolicy();
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['nom' => $roleName], ['nom' => $roleName]);
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_view_any_admin(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_enseignant(): void
    {
        $user = $this->createUserWithRole('enseignant');
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_parent(): void
    {
        $user = $this->createUserWithRole('parent');
        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_view_admin(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($this->policy->view($user));
    }

    public function test_view_enseignant(): void
    {
        $user = $this->createUserWithRole('enseignant');
        $this->assertTrue($this->policy->view($user));
    }

    public function test_view_parent(): void
    {
        $user = $this->createUserWithRole('parent');
        $this->assertFalse($this->policy->view($user));
    }

    public function test_gerer_super_admin(): void
    {
        $user = $this->createUserWithRole('super_admin');
        $this->assertTrue($this->policy->gerer($user));
    }

    public function test_gerer_admin(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($this->policy->gerer($user));
    }

    public function test_exporter_admin(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($this->policy->exporter($user));
    }

    public function test_exporter_comptable(): void
    {
        $user = $this->createUserWithRole('comptable');
        $this->assertTrue($this->policy->exporter($user));
    }

    public function test_exporter_enseignant(): void
    {
        $user = $this->createUserWithRole('enseignant');
        $this->assertFalse($this->policy->exporter($user));
    }
}
