<?php
namespace App\Services\Marketplace;

use App\Models\Tenant;

class CommissionService
{
    private const PLAN_RATES = [
        'gratuit' => 0.10,
        'pro' => 0.07,
        'premium' => 0.05,
    ];

    private const DEFAULT_RATE = 0.07;

    public function calculateCommission(float $montant, Tenant $tenant): float
    {
        $plan = $tenant->plan_abonnement ?? 'pro';
        $rate = self::PLAN_RATES[$plan] ?? self::DEFAULT_RATE;

        return round($montant * $rate, 2);
    }

    public function calculateNetEnseignant(float $montant, float $commission): float
    {
        return round($montant - $commission, 2);
    }
}
