<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ArticleStock;
use Illuminate\Support\Facades\Log;

class AlertesStockCommand extends Command
{
    protected $signature   = 'edugest:alertes-stock';
    protected $description = 'Envoyer alertes pour les articles sous le seuil minimum';

    public function handle(): int
    {
        $this->info('Vérification stock bas...');

        $articles = ArticleStock::where('actif', true)
            ->whereColumn('quantite_stock', '<=', 'quantite_minimum')
            ->get();

        if ($articles->isEmpty()) {
            $this->info('Aucun article en rupture.');
            return Command::SUCCESS;
        }

        $parTenant = $articles->groupBy('tenant_id');

        foreach ($parTenant as $tenantId => $items) {
            $liste = $items->map(fn($a) => "{$a->nom} ({$a->quantite_stock}/{$a->quantite_minimum})")->implode(', ');

            Log::warning("Stock bas tenant {$tenantId}: {$liste}");
        }

        $this->info("{$articles->count()} articles sous le seuil dans {$parTenant->count()} tenant(s).");
        return Command::SUCCESS;
    }
}
