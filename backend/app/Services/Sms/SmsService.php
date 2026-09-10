<?php
namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class SmsService
{
    protected TwilioSmsService $twilio;

    public function __construct()
    {
        $this->twilio = new TwilioSmsService();
    }

    public function send(string $to, string $message, string $channel = 'sms'): array
    {
        $result = $this->twilio->send($to, $message);

        if (!$result['success']) {
            Log::channel($channel)->warning('Fallback: SMS non envoyé via Twilio', [
                'to'    => $to,
                'error' => $result['error'],
            ]);
        }

        return $result;
    }

    public function sendRelanceImpaye(string $destinataire, float $montant, string $dateEcheance): array
    {
        $message = $this->formatRelanceMessage($montant, $dateEcheance);
        return $this->send($destinataire, $message);
    }

    public function sendRappelCours(string $destinataire, string $cours, string $date, string $heure): array
    {
        $message = $this->formatRappelMessage($cours, $date, $heure);
        return $this->send($destinataire, $message);
    }

    public function formatRelanceMessage(float $montant, string $dateEcheance): string
    {
        return sprintf(
            "EduGest DZ — Relance de paiement\nVous avez un impayé de %.2f DZD arrivé à échéance le %s.\nMerci de régulariser votre situation.",
            $montant,
            $dateEcheance
        );
    }

    public function formatRappelMessage(string $cours, string $date, string $heure): string
    {
        return sprintf(
            "EduGest DZ — Rappel de cours\nVous avez un cours de « %s » prévu le %s à %s.\nMerci d'être présent.",
            $cours,
            $date,
            $heure
        );
    }
}
