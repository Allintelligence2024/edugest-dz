<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use App\Models\Facture;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchedulersTest extends TestCase
{
    use RefreshDatabase;

    public function test_commande_sms_absents_sans_absences(): void
    {
        $this->artisan('edugest:sms-absents', ['--date' => today()->format('Y-m-d')])
            ->assertSuccessful();
    }

    public function test_commande_sms_absents_avec_date(): void
    {
        $this->artisan('edugest:sms-absents', ['--date' => '2026-07-03'])
            ->assertSuccessful();
    }

    public function test_commande_relances_impayes(): void
    {
        $this->artisan('edugest:relances-impayes')
            ->assertSuccessful();
    }

    public function test_commande_alertes_stock(): void
    {
        $this->artisan('edugest:alertes-stock')
            ->assertSuccessful();
    }

    public function test_commande_alertes_preventif(): void
    {
        $this->artisan('edugest:alertes-preventif')
            ->assertSuccessful();
    }
}
