<?php

namespace App\Observers;

use App\Models\Bulletin;
use App\Services\ParentNotificationService;

class BulletinObserver
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    public function created(Bulletin $bulletin): void
    {
        if (!$bulletin->eleve_id) return;

        $this->notificationService->bulletinGenere(
            $bulletin->eleve_id,
            $bulletin->trimestre,
            (float) ($bulletin->moyenne_generale ?? 0),
            (int) ($bulletin->rang ?? 1),
            (int) ($bulletin->effectif_classe ?? 1)
        );
    }
}
