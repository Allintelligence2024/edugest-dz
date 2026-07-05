<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\DiagnosticService;
use App\Services\ParentNotificationService;

class NoteObserver
{
    public function __construct(
        private DiagnosticService         $diagnostic,
        private ParentNotificationService  $notificationService,
    ) {}

    public function created(Note $note): void
    {
        if (!$note->note || !$note->eleve_id) return;

        try {
            $diag = $this->diagnostic->analyserEleve($note->eleve_id);
            if ($diag->niveau_global !== 'normal' && $diag->niveau_global !== 'excellent') {
                $this->notificationService->niveauChange(
                    $note->eleve_id,
                    $diag->niveau_global,
                    (float) ($diag->moyenne_generale ?? 0)
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Diagnostic Note: " . $e->getMessage());
        }

        $matiere  = $note->evaluation?->matiere?->nom_fr
            ?? $note->evaluation?->groupe?->matiere?->nom_fr
            ?? 'Matière';
        $noteSur  = $note->evaluation?->note_sur ?? 20;
        $appreciation = $note->appreciation;

        $this->notificationService->notePubliee(
            $note->eleve_id,
            $matiere,
            (float) $note->note,
            (float) $noteSur,
            $appreciation
        );
    }
}
