<?php
namespace Tests\Unit\Services;

use App\Services\Sms\SmsService;
use App\Services\Sms\TwilioSmsService;
use Tests\TestCase;

class TwilioSmsServiceTest extends TestCase
{
    /** @test */
    public function formater_numero_algerien_avec_indicatif_local()
    {
        $this->assertEquals('+213555123456', formaterNumeroAlgerien('0555123456'));
    }

    /** @test */
    public function formater_numero_algerien_avec_indicatif_international()
    {
        $this->assertEquals('+213555123456', formaterNumeroAlgerien('+213555123456'));
    }

    /** @test */
    public function formater_numero_algerien_sans_plus()
    {
        $this->assertEquals('+213555123456', formaterNumeroAlgerien('213555123456'));
    }

    /** @test */
    public function formater_numero_algerien_sans_prefixe()
    {
        $this->assertEquals('+213555123456', formaterNumeroAlgerien('555123456'));
    }

    /** @test */
    public function formater_numero_algerien_invalide()
    {
        $this->assertNull(formaterNumeroAlgerien('invalid'));
    }

    /** @test */
    public function formater_numero_algerien_vide()
    {
        $this->assertNull(formaterNumeroAlgerien(''));
    }

    /** @test */
    public function formater_numero_avec_double_zero()
    {
        $this->assertEquals('+213555123456', formaterNumeroAlgerien('00213555123456'));
    }

    /** @test */
    public function service_gere_exception_quand_non_configure()
    {
        $service = new TwilioSmsService();

        $result = $service->send('0555123456', 'Test');

        $this->assertFalse($result['success']);
        $this->assertEquals('0555123456', $result['to']);
    }

    /** @test */
    public function format_relance_message_contient_montant_et_date()
    {
        $service = new SmsService();

        $message = $service->formatRelanceMessage(15000.50, '15/06/2026');

        $this->assertStringContainsString('15000.50', $message);
        $this->assertStringContainsString('15/06/2026', $message);
        $this->assertStringContainsString('DZD', $message);
    }

    /** @test */
    public function format_rappel_message_contient_cours_date_heure()
    {
        $service = new SmsService();

        $message = $service->formatRappelMessage('Mathématiques', '20/06/2026', '14:30');

        $this->assertStringContainsString('Mathématiques', $message);
        $this->assertStringContainsString('20/06/2026', $message);
        $this->assertStringContainsString('14:30', $message);
    }
}
