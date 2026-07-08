<?php

namespace App\Console\Commands;

use App\Services\JwtSecretRotationService;
use Illuminate\Console\Command;

class RoterJwtSecretCommand extends Command
{
    protected $signature   = 'edugest:jwt-rotate';
    protected $description = 'Effectuer une rotation du secret JWT (sécurité)';

    public function handle(JwtSecretRotationService $service): int
    {
        $this->warn('⚠️  Cette commande va générer un nouveau JWT_SECRET.');
        $this->warn('   Les utilisateurs connectés resteront connectés 24h (période de grâce).');

        if (!$this->confirm('Continuer ?')) {
            $this->info('Annulé.');
            return Command::SUCCESS;
        }

        $result = $service->effectuerRotation();

        $this->info('✅ Rotation effectuée !');
        $this->line('');
        $this->line('<fg=yellow>Nouveau JWT_SECRET (mettre dans les variables d\'environnement) :</>');
        $this->line("<fg=green>{$result['nouveau_secret']}</>");
        $this->line('');
        $this->line("Période de grâce jusqu'à : {$result['grace_until']}");
        $this->line('');
        $this->warn('ACTION REQUISE : Redéployer l\'application avec le nouveau JWT_SECRET !');

        return Command::SUCCESS;
    }
}
