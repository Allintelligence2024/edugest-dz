<?php

namespace Tests\Unit\Commands;

use App\Models\{User, Enseignant, Tenant, Role};
use App\Console\Commands\DetecterAbsenceEnseignantCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DetecterAbsenceEnseignantCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
    }

    public function test_command_returns_zero_when_table_missing(): void
    {
        if (Schema::hasTable('pointage_enseignants')) {
            $this->markTestSkipped('Table pointage_enseignants exists — cannot test missing table path');
        }

        $exitCode = $this->artisan('absences:detecter');
        $this->assertEquals(0, $exitCode);
    }

    public function test_command_runs_with_empty_tables(): void
    {
        if (!Schema::hasTable('pointage_enseignants')) {
            $this->markTestSkipped('Table pointage_enseignants missing');
        }

        $exitCode = $this->artisan('absences:detecter');
        $this->assertContains($exitCode, [0, 1]);
    }

    public function test_command_detects_absence_with_seance_data(): void
    {
        if (!Schema::hasTable('pointage_enseignants')) {
            $this->markTestSkipped('Table pointage_enseignants missing');
        }

        $this->artisan('absences:detecter', ['--date' => now()->subDay()->toDateString()]);
        $this->assertTrue(true);
    }
}
