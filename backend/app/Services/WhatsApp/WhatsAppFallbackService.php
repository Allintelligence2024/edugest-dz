<?php
namespace App\Services\WhatsApp;

use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Log;

class WhatsAppFallbackService
{
    protected WhatsAppService $whatsapp;
    protected SmsService $sms;

    public function __construct()
    {
        $this->whatsapp = new WhatsAppService();
        $this->sms      = new SmsService();
    }

    public function send(string $to, string $message, string $type = 'reminder'): array
    {
        $result = $this->whatsapp->sendTemplate($to, $type === 'reminder' ? 'rappel_cours' : 'relance_paiement', [$message]);

        if ($result['success']) {
            $result['channel'] = 'whatsapp';
            return $result;
        }

        Log::channel('whatsapp')->warning('Fallback WhatsApp → SMS', [
            'to'    => $to,
            'type'  => $type,
            'error' => $result['error'],
        ]);

        $smsResult = $this->sms->send($to, $message);

        return [
            'channel'   => 'sms',
            'success'   => $smsResult['success'],
            'messageId' => $smsResult['messageId'] ?? null,
            'to'        => $to,
            'error'     => $smsResult['error'] ?? null,
        ];
    }
}
