<?php

namespace App\Console\Commands;

use App\Services\AuditChainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 3 — Vérification périodique de la chaîne d'audit.
 *
 * Une chaîne inviolable ne sert à rien si personne ne la vérifie : une
 * altération non détectée équivaut à une absence de chaîne. Cette commande
 * est destinée à tourner quotidiennement (scheduler ou Vercel Cron).
 */
class VerifierAuditChainCommand extends Command
{
    protected $signature = 'audit:verify {--json : Sortie machine}';

    protected $description = "Vérifie l'intégrité complète de la chaîne d'audit (hachages + signatures HMAC)";

    public function handle(AuditChainService $service): int
    {
        $resultats = $service->verifierIntegriteComplete();

        if ($this->option('json')) {
            $this->line(json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $resultats['valide'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Blocs analysés : {$resultats['total']}");

        if ($resultats['valide']) {
            $this->info('✅ Chaîne d\'audit intègre — aucun bloc altéré.');

            return self::SUCCESS;
        }

        $this->error(sprintf('❌ %d bloc(s) invalide(s) détecté(s) !', count($resultats['invalides'])));

        $this->table(
            ['Bloc', 'Raison'],
            array_map(
                fn ($i) => [$i['bloc_numero'], $i['raison']],
                array_slice($resultats['invalides'], 0, 50)
            )
        );

        // Une chaîne rompue est un incident de sécurité, pas un simple warning.
        Log::critical('AuditChain: intégrité compromise', [
            'total'         => $resultats['total'],
            'nb_invalides'  => count($resultats['invalides']),
            'premiers_blocs'=> array_slice($resultats['invalides'], 0, 10),
        ]);

        return self::FAILURE;
    }
}
