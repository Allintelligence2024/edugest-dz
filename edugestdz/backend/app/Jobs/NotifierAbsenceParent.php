<?php
namespace App\Jobs;

use App\Models\AbsenceJournaliere;
use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifierAbsenceParent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $absenceId
    ) {}

    public function handle(SmsService $sms): void
    {
        $absence = AbsenceJournaliere::with(['eleve.parents'])->find($this->absenceId);

        if (!$absence || $absence->sms_parent_envoye) return;

        $eleve   = $absence->eleve;
        $date    = $absence->date_absence->format('d/m/Y');
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "est absent(e) ce {$date}. Contactez l'établissement si cela est une erreur.";

        $sent = false;
        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                $sms->send($parent->telephone_1, $message);
                $sent = true;
            }
        }

        if ($sent) {
            $absence->update([
                'sms_parent_envoye' => true,
                'sms_envoye_at'     => now(),
            ]);
        }

        Log::info("AbsenceNotification: élève {$eleve->nom} {$eleve->prenom} — SMS " . ($sent ? 'envoyé' : 'non envoyé'));
    }
}
