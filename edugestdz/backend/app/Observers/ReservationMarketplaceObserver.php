<?php

namespace App\Observers;

use App\Models\ReservationMarketplace;
use App\Services\FirebaseService;

class ReservationMarketplaceObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function updated(ReservationMarketplace $reservation): void
    {
        if ($reservation->statut !== 'confirmee') return;
        if ($reservation->getOriginal('statut') === 'confirmee') return;

        $offre = $reservation->offre;
        $date  = \Carbon\Carbon::parse($reservation->date_souhaitee)
            ->format('d/m/Y à H:i');

        $this->firebase->notifyUser(
            $reservation->parent_id,
            '✅ Réservation confirmée !',
            "Votre réservation pour « {$offre?->titre} » le {$date} est confirmée.",
            ['type' => 'reservation', 'reservation_id' => $reservation->id]
        );
    }
}
