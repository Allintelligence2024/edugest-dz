<?php

namespace App\Console\Commands;

use App\Services\KillSwitchService;
use Illuminate\Console\Command;

class KillSwitchOffCommand extends Command
{
    protected $signature = 'edugest:killswitch-off {--force : Ne pas demander confirmation}';
    protected $description = 'Désactiver le KillSwitch en urgence (Redis + BDD)';

    public function handle(KillSwitchService $service): int
    {
        $isActive = $service->estActif();

        if (!$isActive) {
            $this->info('Le KillSwitch est déjà inactif.');
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Désactiver le KillSwitch ? Cette action est irréversible.')) {
                $this->info('Annulé.');
                return Command::SUCCESS;
            }
        }

        $service->desactiverUrgence();

        $this->info('KillSwitch désactivé avec succès (Redis + BDD).');
        return Command::SUCCESS;
    }
}
