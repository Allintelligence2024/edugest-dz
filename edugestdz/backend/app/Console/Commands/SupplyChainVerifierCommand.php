<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SupplyChainVerifierCommand extends Command
{
    protected $signature = 'edugest:supply-chain-verify';
    protected $description = 'Verifie l\'integrite des dependances (Supply Chain Security)';

    public function handle(): int
    {
        $this->info('SupplyChainVerifier: verification des dependances...');

        $composerLock = base_path('composer.lock');

        if (!file_exists($composerLock)) {
            $this->warn('SupplyChainVerifier: composer.lock introuvable.');
            return Command::SUCCESS;
        }

        $lockHash = hash_file('sha256', $composerLock);
        $this->line("composer.lock SHA256: {$lockHash}");

        try {
            $result = Process::run('composer audit 2>&1');

            if ($result->successful()) {
                $output = trim($result->output());
                if (empty($output) || str_contains($output, 'No known vulnerabilities')) {
                    $this->info('SupplyChainVerifier: aucune vulnerabilite connue.');
                } else {
                    $this->warn('SupplyChainVerifier: vulnerabilites detectees:');
                    $this->line($output);
                }
            } else {
                $this->warn('SupplyChainVerifier: composer audit indisponible — verification du lock file uniquement.');
                $this->line("Hash du lock file: {$lockHash}");
            }
        } catch (\Throwable $e) {
            $this->warn('SupplyChainVerifier: impossible de lancer composer audit — fallback hash lock file.');
            $this->line("Hash du lock file: {$lockHash}");
        }

        $storedHash = cache()->get('supply_chain:lock_hash');

        if ($storedHash && $storedHash !== $lockHash) {
            $this->warn('SupplyChainVerifier: ATTENTION — le composer.lock a change depuis la derniere verification!');
            $this->line("Ancien hash: {$storedHash}");
            $this->line("Nouveau hash: {$lockHash}");
        }

        cache()->put('supply_chain:lock_hash', $lockHash, now()->addDay());

        $this->info('SupplyChainVerifier: verification terminee.');

        return Command::SUCCESS;
    }
}
