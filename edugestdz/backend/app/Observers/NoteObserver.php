<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\FirebaseService;

class NoteObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(Note $note): void
    {
        if (!$note->note) return;

        $eleve = $note->eleve;
        if (!$eleve) return;

        $matiere = $note->evaluation?->groupe?->matiere?->nom_fr ?? 'une matière';

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '📝 Nouvelle note',
            "{$eleve->prenom} a obtenu {$note->note}/20 en {$matiere}.",
            ['type' => 'note', 'eleve_id' => $eleve->id]
        );
    }
}
