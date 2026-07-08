<?php

namespace App\Console\Commands;

use App\Services\ImmutableAuditService;
use Illuminate\Console\Command;

class ExporterAuditJournalierCommand extends Command
{
    protected $signature   = 'edugest:audit-export {--date= : Date YYYY-MM-DD}';
    protected $description = "Exporter et signer les logs d'audit de la journée";

    public function handle(ImmutableAuditService $service): int
    {
        $date   = $this->option('date') ?? now()->subDay()->format('Y-m-d');
        $result = $service->exporterJournalier(null, $date);

        $this->info("✅ Audit exporté: {$result['exportes']} entrées · Hash: {$result['hash']}");
        return Command::SUCCESS;
    }
}
