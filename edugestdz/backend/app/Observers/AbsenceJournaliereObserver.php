<?php

namespace App\Observers;

use App\Models\AbsenceJournaliere;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class AbsenceJournaliereObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(AbsenceJournaliere $absence): void
    {
        $eleve = $absence->eleve;
        if (!$eleve) return;

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '⚠️ Absence signalée',
            "{$eleve->prenom} {$eleve->nom} est absent(e) le {$absence->date_absence}.",
            ['type' => 'absence', 'eleve_id' => $eleve->id, 'absence_id' => $absence->id]
        );

        Log::info("Push absence envoyé pour élève {$eleve->id}");
    }
}
