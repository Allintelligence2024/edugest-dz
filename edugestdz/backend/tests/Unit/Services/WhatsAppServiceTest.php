<?php
namespace Tests\Unit\Services;

use App\Services\Sms\SmsService;
use App\Services\WhatsApp\WhatsAppFallbackService;
use App\Services\WhatsApp\WhatsAppService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    private function mockWhatsAppService(int $statusCode, array $body): WhatsAppService
    {
        $mock = new MockHandler([new Response($statusCode, [], json_encode($body))]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new WhatsAppService();
        $reflection = new \ReflectionClass($service);
        $httpProp = $reflection->getProperty('http');
        $httpProp->setAccessible(true);
        $httpProp->setValue($service, $client);

        return $service;
    }

    public function test_send_template_sends_correct_payload(): void
    {
        $service = $this->mockWhatsAppService(200, [
            'messages' => [['id' => 'wamid.test123']],
        ]);

        $result = $service->sendTemplate('0555123456', 'rappel_cours', ['Ali', 'Maths', '2026-06-20', '10:00']);

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.test123', $result['messageId']);
    }

    public function test_send_template_returns_error_on_failure(): void
    {
        $service = $this->mockWhatsAppService(400, [
            'error' => ['message' => 'Invalid template'],
        ]);

        $result = $service->sendTemplate('0555123456', 'rappel_cours', ['Ali']);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_send_template_returns_error_on_invalid_number(): void
    {
        $service = new WhatsAppService();

        $result = $service->sendTemplate('invalid', 'rappel_cours', []);

        $this->assertFalse($result['success']);
        $this->assertEquals('Numéro de téléphone invalide', $result['error']);
    }

    public function test_normalize_number_formats_algerian_number(): void
    {
        $service = new WhatsAppService();

        $this->assertEquals('+213555123456', $service->normalizeNumber('0555123456'));
        $this->assertEquals('+213555123456', $service->normalizeNumber('+213555123456'));
        $this->assertEquals('+213555123456', $service->normalizeNumber('213555123456'));
        $this->assertNull($service->normalizeNumber('123'));
    }

    public function test_send_text_returns_error_on_invalid_number(): void
    {
        $service = new WhatsAppService();

        $result = $service->sendText('invalid', 'test');

        $this->assertFalse($result['success']);
        $this->assertEquals('Numéro de téléphone invalide', $result['error']);
    }

    public function test_fallback_service_calls_sms_on_whatsapp_failure(): void
    {
        $service = $this->getMockBuilder(WhatsAppFallbackService::class)
            ->onlyMethods([])
            ->disableOriginalConstructor()
            ->getMock();

        $whatsappMock = $this->createMock(WhatsAppService::class);
        $whatsappMock->method('sendTemplate')->willReturn([
            'success'   => false,
            'messageId' => null,
            'to'        => '0555123456',
            'error'     => 'API Error',
        ]);

        $smsMock = $this->createMock(SmsService::class);
        $smsMock->method('send')->willReturn([
            'success'   => true,
            'messageId' => 'SM123',
            'to'        => '0555123456',
            'error'     => null,
        ]);

        $reflection = new \ReflectionClass(WhatsAppFallbackService::class);

        $fallback = $reflection->newInstanceWithoutConstructor();

        $whatsappProp = $reflection->getProperty('whatsapp');
        $whatsappProp->setAccessible(true);
        $whatsappProp->setValue($fallback, $whatsappMock);

        $smsProp = $reflection->getProperty('sms');
        $smsProp->setAccessible(true);
        $smsProp->setValue($fallback, $smsMock);

        $result = $fallback->send('0555123456', 'Test message', 'reminder');

        $this->assertEquals('sms', $result['channel']);
        $this->assertTrue($result['success']);
    }

    public function test_send_reminder_delegates_to_send_template(): void
    {
        $service = $this->mockWhatsAppService(200, [
            'messages' => [['id' => 'wamid.reminder']],
        ]);

        $result = $service->sendReminder('0555123456', 'Ali', 'Maths', '2026-06-20', '10:00');

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.reminder', $result['messageId']);
    }

    public function test_send_payment_reminder_delegates_to_send_template(): void
    {
        $service = $this->mockWhatsAppService(200, [
            'messages' => [['id' => 'wamid.payment']],
        ]);

        $result = $service->sendPaymentReminder('0555123456', 'Ali', 5000.00, '2026-07-01');

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.payment', $result['messageId']);
    }

    public function test_send_bulletin_link_delegates_to_send_template(): void
    {
        $service = $this->mockWhatsAppService(200, [
            'messages' => [['id' => 'wamid.bulletin']],
        ]);

        $result = $service->sendBulletinLink('0555123456', 'Ali', 'https://edugest.dz/bulletins/abc123');

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.bulletin', $result['messageId']);
    }
}
