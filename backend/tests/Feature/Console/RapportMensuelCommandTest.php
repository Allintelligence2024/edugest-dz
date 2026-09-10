<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RapportMensuelCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::factory()->create(['statut' => 'actif']);
    }

    public function test_command_runs_without_error(): void
    {
        Mail::fake();

        $this->artisan('edugest:rapport-mensuel')
            ->assertExitCode(0);
    }

    public function test_command_with_specific_month(): void
    {
        Mail::fake();

        $this->artisan('edugest:rapport-mensuel', [
            now()->subMonth()->month,
            now()->subMonth()->year,
        ])->assertExitCode(0);
    }

    public function test_command_sends_no_real_emails(): void
    {
        Mail::fake();

        $this->artisan('edugest:rapport-mensuel')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_command_handles_no_tenant(): void
    {
        Mail::fake();

        $this->artisan('edugest:rapport-mensuel', [1, 2025])
            ->assertExitCode(0);
    }
}
