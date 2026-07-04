<?php

namespace App\Observers;

use App\Models\Bulletin;
use App\Services\FirebaseService;

class BulletinObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(Bulletin $bulletin): void
    {
        $eleve = $bulletin->eleve;
        if (!$eleve) return;

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '📄 Bulletin disponible',
            "Le bulletin de {$eleve->prenom} ({$bulletin->trimestre}) est prêt. Moyenne : {$bulletin->moyenne_generale}/20.",
            ['type' => 'bulletin', 'eleve_id' => $eleve->id, 'bulletin_id' => $bulletin->id]
        );
    }
}
