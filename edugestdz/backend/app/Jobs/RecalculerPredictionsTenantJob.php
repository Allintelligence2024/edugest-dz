<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\PredictionEchecService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculerPredictionsTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly ?string $tenantId = null,
    ) {}

    public function handle(PredictionEchecService $service): void
    {
        $tenants = $this->tenantId
            ? Tenant::where('id', $this->tenantId)->where('statut', 'actif')->get()
            : Tenant::where('statut', 'actif')->get();

        $total = 0;

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            $predictions = $service->predireTenant($tenant->id);
            $count = count($predictions);
            $total += $count;

            $critiques = collect($predictions)->filter(fn($p) => $p['niveau_risque'] === 'critique')->count();

            Log::info("RecalculerPredictions terminé pour tenant {$tenant->id}", [
                'tenant'     => $tenant->nom_etablissement,
                'total'      => $count,
                'critiques'  => $critiques,
            ]);
        }

        Log::info("RecalculerPredictionsGlobal terminé", ['total_eleves' => $total]);
    }
}
