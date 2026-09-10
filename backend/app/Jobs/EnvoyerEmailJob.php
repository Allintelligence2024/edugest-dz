<?php

namespace App\Jobs;

use App\Models\{Campagne, CampagneDestinataire, User};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnvoyerEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Campagne $campagne,
        public User $user,
        public CampagneDestinataire $destinataire
    ) {}

    public function handle(): void
    {
        try {
            // Driver réel (Mailgun/SendGrid/SMTP) à brancher ici
            Log::info('[Campagne Email]', [
                'to'      => $this->user->email,
                'subject' => $this->campagne->titre,
                'message' => $this->campagne->message,
            ]);

            $this->destinataire->update([
                'statut'    => 'envoyé',
                'envoye_le' => now(),
            ]);
        } catch (\Exception $e) {
            $this->destinataire->update([
                'statut' => 'échec',
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
