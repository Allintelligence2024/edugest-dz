<?php

namespace Tests\Unit\Services;

use App\Services\NotificationTimingService;
use Carbon\Carbon;
use Tests\TestCase;

class NotificationTimingTest extends TestCase
{
    private NotificationTimingService $timing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timing = new NotificationTimingService();
    }

    public function test_est_en_plage_horaire_pendant_heures_bureau(): void
    {
        $moment = Carbon::create(2026, 7, 10, 10, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->estEnPlageHoraire($moment));
    }

    public function test_est_en_plage_horaire_debut_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 7, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->estEnPlageHoraire($moment));
    }

    public function test_est_en_plage_horaire_fin_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 19, 59, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->estEnPlageHoraire($moment));
    }

    public function test_est_en_plage_horaire_apres_fin(): void
    {
        $moment = Carbon::create(2026, 7, 10, 20, 0, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->estEnPlageHoraire($moment));
    }

    public function test_est_en_plage_horaire_avant_debut(): void
    {
        $moment = Carbon::create(2026, 7, 10, 6, 59, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->estEnPlageHoraire($moment));
    }

    public function test_est_en_heures_nuit(): void
    {
        $moment = Carbon::create(2026, 7, 10, 23, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->estEnHeuresNuit($moment));
    }

    public function test_est_en_heures_nuit_tard_nuit(): void
    {
        $moment = Carbon::create(2026, 7, 10, 3, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->estEnHeuresNuit($moment));
    }

    public function test_est_en_heures_nuit_journee(): void
    {
        $moment = Carbon::create(2026, 7, 10, 12, 0, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->estEnHeuresNuit($moment));
    }

    public function test_doit_envoyer_push_urgence_toujours(): void
    {
        $moment = Carbon::create(2026, 7, 10, 2, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->doitEnvoyerPush(true, $moment));
    }

    public function test_doit_envoyer_push_sans_urgence_hors_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 2, 0, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->doitEnvoyerPush(false, $moment));
    }

    public function test_doit_envoyer_sms_urgence_toujours(): void
    {
        $moment = Carbon::create(2026, 7, 10, 23, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->doitEnvoyerSMS(true, $moment));
    }

    public function test_doit_envoyer_sms_nuit_sans_urgence(): void
    {
        $moment = Carbon::create(2026, 7, 10, 23, 0, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->doitEnvoyerSMS(false, $moment));
    }

    public function test_doit_envoyer_email_nuit(): void
    {
        $moment = Carbon::create(2026, 7, 10, 23, 0, 0, 'Africa/Algiers');
        $this->assertFalse($this->timing->doitEnvoyerEmail(false, $moment));
    }

    public function test_doit_envoyer_email_journee(): void
    {
        $moment = Carbon::create(2026, 7, 10, 12, 0, 0, 'Africa/Algiers');
        $this->assertTrue($this->timing->doitEnvoyerEmail(false, $moment));
    }

    public function test_delai_avant_envoi_dans_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 10, 0, 0, 'Africa/Algiers');
        $this->assertEquals(0, $this->timing->getDelaiAvantEnvoi($moment));
    }

    public function test_delai_avant_envoi_apres_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 22, 0, 0, 'Africa/Algiers');
        $delai = $this->timing->getDelaiAvantEnvoi($moment);
        $this->assertGreaterThan(0, $delai);
        $this->assertLessThanOrEqual(600, $delai);
    }

    public function test_delai_avant_envoi_avant_plage(): void
    {
        $moment = Carbon::create(2026, 7, 10, 4, 0, 0, 'Africa/Algiers');
        $delai = $this->timing->getDelaiAvantEnvoi($moment);
        $this->assertGreaterThan(0, $delai);
        $this->assertLessThanOrEqual(300, $delai);
    }

    public function test_get_plage_horaire_label(): void
    {
        $label = $this->timing->getPlageHoraireLabel();
        $this->assertIsString($label);
        $this->assertStringContainsString('7h', $label);
        $this->assertStringContainsString('20h', $label);
    }
}
