<?php

namespace App\Console\Commands;

use App\Services\JwtBlacklistService;
use Illuminate\Console\Command;

class NettoyerJwtBlacklistCommand extends Command
{
    protected $signature   = 'edugest:nettoyer-jwt-blacklist';
    protected $description = 'Supprimer les tokens JWT expirés de la blacklist';

    public function handle(JwtBlacklistService $service): int
    {
        $supprimés = $service->nettoyerExpires();
        $this->info("✅ {$supprimés} token(s) JWT expirés supprimés de la blacklist.");
        return Command::SUCCESS;
    }
}
