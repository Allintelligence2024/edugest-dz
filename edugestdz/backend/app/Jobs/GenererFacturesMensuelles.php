<?php

namespace App\Jobs;

use App\Services\FacturationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenererFacturesMensuelles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max
    public int $tries   = 1;   // pas de retry — idempotent

    public function __construct(
        public readonly int    $mois,
        public readonly int    $annee,
        public readonly float  $tarifScolarite,
        public readonly string $tenantId,
    ) {}

    public function handle(FacturationService $service): void
    {
        // Restaurer le contexte tenant dans le job
        config(['tenant.current_id' => $this->tenantId]);

        $resultats = $service->genererFacturesMensuelles(
            $this->mois,
            $this->annee,
            $this->tarifScolarite
        );

        Log::info("GenererFacturesMensuelles terminé", [
            'mois'      => $this->mois,
            'annee'     => $this->annee,
            'tenant_id' => $this->tenantId,
            'resultats' => $resultats,
        ]);

        if (!empty($resultats['erreurs'])) {
            Log::warning("Erreurs lors de la génération de factures", [
                'erreurs' => $resultats['erreurs'],
            ]);
        }
    }
}
