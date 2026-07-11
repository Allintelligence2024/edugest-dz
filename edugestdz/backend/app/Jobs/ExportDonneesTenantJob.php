<?php

namespace App\Jobs;

use App\Services\NotificationInAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Storage};

class ExportDonneesTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600;

    public function __construct(
        private string $tenantId,
        private string $jobId,
        private string $userId
    ) {}

    public function handle(NotificationInAppService $notif): void
    {
        config(['tenant.current_id' => $this->tenantId]);

        $tables = ['eleves', 'enseignants', 'cours', 'seances', 'factures',
                   'paiements', 'notes', 'bulletins', 'absences_journalieres', 'billets'];

        $donnees = [];
        foreach ($tables as $table) {
            try {
                $donnees[$table] = DB::table($table)
                    ->where('tenant_id', $this->tenantId)
                    ->get()->toArray();
            } catch (\Throwable) {
                $donnees[$table] = [];
            }
        }

        $nomFichier = "export_tenant_{$this->tenantId}_" . date('Y-m-d') . ".json";
        $contenu    = json_encode([
            'tenant_id'   => $this->tenantId,
            'export_date' => now()->toIso8601String(),
            'loi'         => 'Loi 18-07 Algérie — Droit à la portabilité',
            'donnees'     => $donnees,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $chemin = "exports/{$this->tenantId}/{$nomFichier}";
        Storage::disk('local')->put($chemin, $contenu);

        $notif->creer(
            userId:   $this->userId,
            type:     'export_rgpd_pret',
            titre:    '📦 Export RGPD prêt',
            corps:    "Vos données ont été exportées avec succès. Fichier : {$nomFichier}",
            meta:     ['chemin' => $chemin, 'action_url' => "/api/v1/rgpd/telecharger/{$this->jobId}"],
            tenantId: $this->tenantId
        );
    }
}
