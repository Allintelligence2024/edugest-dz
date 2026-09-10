<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculerPredictionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::factory()->create(['statut' => 'actif']);
    }

    public function test_command_returns_success(): void
    {
        $this->artisan('edugest:recalculer-predictions')
            ->assertSuccessful();
    }

    public function test_command_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('edugest:recalculer-predictions');

        Queue::assertPushed(\App\Jobs\RecalculerPredictionsTenantJob::class);
    }

    public function test_command_with_tenant_option(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['statut' => 'actif']);

        $this->artisan("edugest:recalculer-predictions --tenant={$tenant->id}")
            ->assertSuccessful();

        Queue::assertPushed(\App\Jobs\RecalculerPredictionsTenantJob::class, function ($job) use ($tenant) {
            return $job->tenantId === $tenant->id;
        });
    }
}
