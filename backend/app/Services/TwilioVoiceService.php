<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioVoiceService
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;
    protected string  $langue;
    protected string  $voix;

    public function __construct()
    {
        $this->sid    = config('services.twilio.sid');
        $this->token  = config('services.twilio.token');
        $this->from   = config('services.twilio.from');
        $this->langue = config('services.twilio.voice_langue', 'fr-FR');
        $this->voix   = config('services.twilio.voice_nom', 'alice');
    }

    /**
     * Passer un appel vocal avec message TTS via Twilio.
     */
    public function appeler(string $numero, string $message): array
    {
        $numero = $this->formaterNumero($numero);

        if ($numero === null) {
            return ['success' => false, 'to' => '', 'error' => 'Numéro invalide'];
        }

        if (!$this->sid || !$this->token || !$this->from) {
            Log::channel('sms')->error('Twilio Voice non configuré');
            return ['success' => false, 'to' => $numero, 'error' => 'Twilio non configuré'];
        }

        try {
            $twiml = '<Response><Say language="' . $this->langue . '" voice="' . $this->voix . '">'
                     . e($message)
                     . '</Say></Response>';

            $response = Http::withBasicAuth($this->sid, $this->token)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Calls.json", [
                    'To'      => $numero,
                    'From'    => $this->from,
                    'Twiml'   => $twiml,
                    'Timeout' => 30,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                Log::channel('sms')->info('Appel vocal lancé', [
                    'to'       => $numero,
                    'call_sid' => $body['sid'] ?? null,
                    'status'   => $body['status'] ?? null,
                ]);
                return [
                    'success'  => true,
                    'call_sid' => $body['sid'] ?? null,
                    'to'       => $numero,
                    'status'   => $body['status'] ?? null,
                    'error'    => null,
                ];
            }

            $error = $response->json('message') ?? "Erreur HTTP {$response->status()}";
            Log::channel('sms')->error('Échec appel vocal Twilio', ['to' => $numero, 'error' => $error]);
            return ['success' => false, 'to' => $numero, 'error' => $error];

        } catch (\Throwable $e) {
            Log::channel('sms')->error('Exception appel vocal', [
                'to'    => $numero,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'to' => $numero, 'error' => $e->getMessage()];
        }
    }

    protected function formaterNumero(string $numero): ?string
    {
        if (function_exists('formaterNumeroAlgerien')) {
            return formaterNumeroAlgerien($numero);
        }

        $clean = preg_replace('/[^0-9+]/', '', $numero);
        if ($clean === '') return null;
        if (str_starts_with($clean, '+213') && strlen($clean) === 13) return $clean;
        if (str_starts_with($clean, '0') && strlen($clean) === 10) return '+213' . substr($clean, 1);
        if (strlen($clean) === 9 && preg_match('/^[567]/', $clean)) return '+213' . $clean;

        return null;
    }
}
