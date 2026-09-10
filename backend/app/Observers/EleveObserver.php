<?php

namespace App\Observers;

use App\Models\Eleve;
use Illuminate\Support\Facades\Cache;

class EleveObserver
{
    public function created(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }

    public function updated(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }

    public function deleted(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }
}
