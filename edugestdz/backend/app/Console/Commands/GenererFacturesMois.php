<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FacturationService;
use Illuminate\Console\Command;

class GenererFacturesMois extends Command
{
    protected $signature   = 'factures:generer-mensuel
                              {--mois= : Mois (1-12), défaut = mois précédent}
                              {--annee= : Année, défaut = année courante}
                              {--tarif= : Tarif scolarité mensuel en DA (défaut 0)}';

    protected $description = 'Génère les factures mensuelles pour tous les élèves actifs (scolarité + transport + cantine)';

    public function handle(FacturationService $service): int
    {
        $mois  = (int) ($this->option('mois')  ?? now()->subMonth()->month);
        $annee = (int) ($this->option('annee') ?? now()->year);
        $tarif = (float) ($this->option('tarif') ?? 0);

        $this->info("Génération des factures {$mois}/{$annee} pour tous les tenants actifs...");

        $totalGenerees = 0;
        $totalIgnorees = 0;

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use (
            $service, $mois, $annee, $tarif, &$totalGenerees, &$totalIgnorees
        ) {
            config(['tenant.current_id' => $tenant->id]);

            $this->line("  → {$tenant->nom_etablissement}");

            $resultats = $service->genererFacturesMensuelles($mois, $annee, $tarif);

            $totalGenerees += $resultats['generees'];
            $totalIgnorees += $resultats['ignorees'];

            $this->line("    ✅ {$resultats['generees']} générée(s) · ⏭ {$resultats['ignorees']} ignorée(s)");

            if (!empty($resultats['erreurs'])) {
                foreach ($resultats['erreurs'] as $err) {
                    $this->warn("    ⚠ {$err['eleve']} : {$err['erreur']}");
                }
            }
        });

        $this->info("Terminé : {$totalGenerees} facture(s) générée(s), {$totalIgnorees} ignorée(s).");
        return self::SUCCESS;
    }
}
