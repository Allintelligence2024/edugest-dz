<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Http, Cache};
use Illuminate\Support\Str;

class SimulerChargeCommand extends Command
{
    protected $signature   = 'edugest:simuler-charge {--tenants=10 : Nombre de tenants à simuler} {--duree=5 : Durée de la simulation en secondes}';
    protected $description = 'Simuler une charge API sur plusieurs endpoints pour tester les performances';

    public function handle(): int
    {
        $nbTenants = (int) $this->option('tenants');
        $duree     = (int) $this->option('duree');

        $this->info("🔄 Simulation de charge : {$nbTenants} tenants, {$duree}s");
        $this->newLine();

        $tenantIds = $this->resolveTenantIds($nbTenants);

        if (empty($tenantIds)) {
            $this->warn('⚠ Aucun tenant trouvé. Utilisation d\'UUIDs fictifs pour les queries.');
            $tenantIds = array_map(fn() => Str::uuid()->toString(), range(1, $nbTenants));
        }

        $endpoints = [
            '/api/v1/health/ping',
            '/api/v1/health',
            '/api/v1/marketplace/stats',
            '/api/v1/marketplace/recherche',
            '/api/v1/marketplace/featured',
        ];

        $results = [];
        $startTime = microtime(true);

        while ((microtime(true) - $startTime) < $duree) {
            foreach ($tenantIds as $tenantId) {
                config(['tenant.current_id' => $tenantId]);

                foreach ($endpoints as $endpoint) {
                    $reqStart = microtime(true);

                    try {
                        $response = Http::timeout(5)->get(url($endpoint));
                        $status = $response->status();
                    } catch (\Throwable) {
                        $status = 0;
                    }

                    $latency = round((microtime(true) - $reqStart) * 1000, 2);

                    $results[] = [
                        'endpoint' => $endpoint,
                        'status'   => $status,
                        'latency'  => $latency,
                    ];
                }
            }
        }

        $this->displayResults($results);

        return Command::SUCCESS;
    }

    private function resolveTenantIds(int $limit): array
    {
        try {
            return DB::table('tenants')
                ->where('statut', 'actif')
                ->limit($limit)
                ->pluck('id')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function displayResults(array $results): void
    {
        if (empty($results)) {
            $this->warn('Aucune requête exécutée.');
            return;
        }

        $grouped = collect($results)->groupBy('endpoint');

        $this->info('📊 Résultats par endpoint :');
        $this->newLine();

        $totalReqs = 0;
        $totalErrors = 0;
        $totalLatency = 0;

        foreach ($grouped as $endpoint => $requests) {
            $count = $requests->count();
            $errors = $requests->where('status', 0)->count();
            $avgLatency = round($requests->avg('latency'), 2);
            $maxLatency = round($requests->max('latency'), 2);
            $minLatency = round($requests->min('latency'), 2);

            $errorRate = $count > 0 ? round(($errors / $count) * 100, 1) : 0;

            $this->line("  <info>{$endpoint}</info>");
            $this->line("    Requêtes : {$count} | Erreurs : {$errors} ({$errorRate}%)");
            $this->line("    Latence  : avg={$avgLatency}ms min={$minLatency}ms max={$maxLatency}ms");

            $totalReqs += $count;
            $totalErrors += $errors;
            $totalLatency += $requests->sum('latency');
        }

        $this->newLine();
        $this->info("📈 Total : {$totalReqs} requêtes | {$totalErrors} erreurs | Latence moyenne : " . round($totalLatency / $totalReqs, 2) . "ms");
    }
}
