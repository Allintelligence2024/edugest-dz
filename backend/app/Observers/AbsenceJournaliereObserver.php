<?php

namespace App\Observers;

use App\Models\AbsenceJournaliere;
use App\Services\ParentNotificationService;

class AbsenceJournaliereObserver
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    public function created(AbsenceJournaliere $absence): void
    {
        $eleve = $absence->eleve;
        if (!$eleve) return;

        $this->notificationService->absenceSignalee(
            $eleve->id,
            $absence->date_absence instanceof \Carbon\Carbon
                ? $absence->date_absence->format('d/m/Y')
                : $absence->date_absence,
            $absence->motif
        );

        $absence->update(['sms_envoye' => true]);
    }
}
