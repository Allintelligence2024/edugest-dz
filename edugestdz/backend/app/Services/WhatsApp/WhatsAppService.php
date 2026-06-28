<?php
namespace App\Services\WhatsApp;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiToken;
    protected string $phoneId;
    protected string $apiUrl;
    protected Client $http;

    public function __construct()
    {
        $this->apiToken = config('services.whatsapp.api_token');
        $this->phoneId  = config('services.whatsapp.phone_id');
        $this->apiUrl   = config('services.whatsapp.api_url');
        $this->http     = new Client([
            'base_uri' => rtrim($this->apiUrl, '/') . '/',
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function sendTemplate(string $to, string $templateName, array $parameters = []): array
    {
        $numero = $this->normalizeNumber($to);

        if ($numero === null) {
            return $this->error('Numéro de téléphone invalide', $to);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $numero,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => 'fr'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => $p], $parameters),
                    ],
                ],
            ],
        ];

        return $this->sendRequest($numero, $payload);
    }

    public function sendText(string $to, string $message): array
    {
        $numero = $this->normalizeNumber($to);

        if ($numero === null) {
            return $this->error('Numéro de téléphone invalide', $to);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $numero,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ];

        return $this->sendRequest($numero, $payload);
    }

    public function sendReminder(string $to, string $studentName, string $courseName, string $date, string $time): array
    {
        return $this->sendTemplate($to, 'rappel_cours', [$studentName, $courseName, $date, $time]);
    }

    public function sendPaymentReminder(string $to, string $studentName, float $amount, string $dueDate): array
    {
        return $this->sendTemplate($to, 'relance_paiement', [$studentName, number_format($amount, 2), $dueDate]);
    }

    public function sendBulletinLink(string $to, string $studentName, string $bulletinUrl): array
    {
        return $this->sendTemplate($to, 'bulletin_disponible', [$studentName, $bulletinUrl]);
    }

    public function normalizeNumber(string $number): ?string
    {
        return formaterNumeroAlgerien($number);
    }

    protected function sendRequest(string $numero, array $payload): array
    {
        try {
            $response = $this->http->post("{$this->phoneId}/messages", [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $messageId = $body['messages'][0]['id'] ?? null;

            Log::channel('whatsapp')->info('Message WhatsApp envoyé', [
                'to'         => $numero,
                'template'   => $payload['template']['name'] ?? 'text',
                'message_id' => $messageId,
            ]);

            return [
                'success'   => true,
                'messageId' => $messageId,
                'to'        => $numero,
                'error'     => null,
            ];
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Échec envoi WhatsApp', [
                'to'    => $numero,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $numero);
        }
    }

    protected function error(string $error, ?string $to): array
    {
        return [
            'success'   => false,
            'messageId' => null,
            'to'        => $to,
            'error'     => $error,
        ];
    }
}
