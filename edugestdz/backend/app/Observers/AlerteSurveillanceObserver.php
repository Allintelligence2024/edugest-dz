<?php

namespace App\Observers;

use App\Models\AlerteSurveillance;
use App\Models\CameraConfig;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;

class AlerteSurveillanceObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(AlerteSurveillance $alerte): void
    {
        Cache::forget("tenant_{$alerte->tenant_id}_alertes_stats");
    }

    public function updated(AlerteSurveillance $alerte): void
    {
        Cache::forget("tenant_{$alerte->tenant_id}_alertes_stats");
    }
}
