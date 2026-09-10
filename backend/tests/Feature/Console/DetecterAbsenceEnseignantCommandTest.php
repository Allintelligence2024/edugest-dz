<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DetecterAbsenceEnseignantCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::factory()->create(['statut' => 'actif']);
    }

    public function test_command_returns_zero_when_table_missing(): void
    {
        if (Schema::hasTable('pointage_enseignants')) {
            $this->markTestSkipped('Table pointage_enseignants exists');
        }

        $this->artisan('edugest:detecter-absences-enseignants')
            ->assertSuccessful();
    }

    public function test_command_runs_with_empty_tables(): void
    {
        if (!Schema::hasTable('pointage_enseignants')) {
            $this->markTestSkipped('Table pointage_enseignants missing');
        }

        $this->artisan('edugest:detecter-absences-enseignants')
            ->assertExitCode(0);
    }
}
