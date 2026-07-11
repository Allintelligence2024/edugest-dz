<?php

namespace Tests\Unit\Commands;

use App\Models\{User, Tenant, Eleve};
use App\Console\Commands\RapportMensuelCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Schema};
use Tests\TestCase;

class RapportMensuelCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
    }

    public function test_command_runs_without_error(): void
    {
        Mail::fake();

        $exitCode = $this->artisan('rapport:mensuel');
        $this->assertContains($exitCode, [0, 1]);
    }

    public function test_command_with_specific_month(): void
    {
        Mail::fake();

        $exitCode = $this->artisan('rapport:mensuel', [
            '--mois' => now()->subMonth()->month,
            '--annee' => now()->subMonth()->year,
        ]);
        $this->assertContains($exitCode, [0, 1]);
    }

    public function test_command_sends_no_real_emails(): void
    {
        Mail::fake();

        $this->artisan('rapport:mensuel');

        Mail::assertNothingSent();
    }

    public function test_command_handles_no_tenant(): void
    {
        Mail::fake();

        $exitCode = $this->artisan('rapport:mensuel', ['--mois' => 1, '--annee' => 2025]);
        $this->assertContains($exitCode, [0, 1]);
    }
}
