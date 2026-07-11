<?php

namespace App\Console\Commands;

use App\Jobs\RecalculerPredictionsTenantJob;
use Illuminate\Console\Command;

class RecalculerPredictionsCommand extends Command
{
    protected $signature   = 'edugest:recalculer-predictions {--tenant=}';
    protected $description = 'Recalculer les prédictions d\'échec scolaire pour tous les tenants actifs';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        RecalculerPredictionsTenantJob::dispatch($tenantId);

        $this->info('✅ Job de recalcul des prédictions lancé');
        return Command::SUCCESS;
    }
}
