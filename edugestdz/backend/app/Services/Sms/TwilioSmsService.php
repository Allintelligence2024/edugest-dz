<?php
namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class TwilioSmsService
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;

    public function __construct()
    {
        $this->sid  = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->from  = config('services.twilio.from');
    }

    public function send(string $to, string $message): array
    {
        $numero = formaterNumeroAlgerien($to);

        if ($numero === null) {
            Log::channel('sms')->warning('Numéro invalide', ['original' => $to]);
            return [
                'success' => false,
                'to'      => $to,
                'error'   => 'Numéro de téléphone invalide',
            ];
        }

        if (!$this->sid || !$this->token || !$this->from) {
            Log::channel('sms')->error('Twilio non configuré');
            return [
                'success' => false,
                'to'      => $to,
                'error'   => 'Twilio non configuré',
            ];
        }

        try {
            $client = new \Twilio\Rest\Client($this->sid, $this->token);

            $sms = $client->messages->create($numero, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::channel('sms')->info('SMS envoyé', [
                'to'         => $numero,
                'message_id' => $sms->sid,
                'status'     => $sms->status,
            ]);

            return [
                'success'    => true,
                'messageId'  => $sms->sid,
                'to'         => $numero,
                'status'     => $sms->status,
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            Log::channel('sms')->error('Échec envoi SMS', [
                'to'      => $numero,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success'   => false,
                'messageId' => null,
                'to'        => $numero,
                'error'     => $e->getMessage(),
            ];
        }
    }

    public function sendBulk(array $recipients, string $message): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $results[] = $this->send($recipient, $message);
        }

        return [
            'success'  => true,
            'sent'     => count(array_filter($results, fn($r) => $r['success'])),
            'failed'   => count(array_filter($results, fn($r) => !$r['success'])),
            'results'  => $results,
        ];
    }

    public function getBalance(): ?float
    {
        if (!$this->sid || !$this->token) {
            return null;
        }

        try {
            $client = new \Twilio\Rest\Client($this->sid, $this->token);
            $balance = $client->balance->fetch();
            return (float) $balance->balance;
        } catch (\Throwable $e) {
            Log::channel('sms')->error('Impossible de récupérer le solde Twilio', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
