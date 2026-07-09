<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommandsProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_config_command(): void
    {
        $this->artisan('edugest:check-config')
            ->assertSuccessful();
    }

    public function test_diagnostic_hebdomadaire(): void
    {
        $this->markTestSkipped('SmsService non implémenté (dépendance du diagnostic-hebdomadaire)');
    }

    public function test_nettoyer_jwt_blacklist(): void
    {
        $this->artisan('edugest:nettoyer-jwt-blacklist')
            ->assertSuccessful();
    }

    public function test_exporter_audit_journalier(): void
    {
        $this->artisan('edugest:audit-export')
            ->assertSuccessful();
    }

    public function test_dead_man_switch(): void
    {
        $this->artisan('edugest:deadman-switch')
            ->assertSuccessful();
    }

    public function test_supply_chain_verify(): void
    {
        $this->artisan('edugest:supply-chain-verify')
            ->assertSuccessful();
    }

    public function test_generer_seances_hebdomadaires(): void
    {
        $this->artisan('edugest:generer-seances')
            ->assertSuccessful();
    }
}
