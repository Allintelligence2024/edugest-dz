<?php

namespace App\Console\Commands;

use App\Models\TrancheFractionnement;
use App\Services\ParentNotificationService;
use Illuminate\Console\Command;

class RelancesEcheanceCommand extends Command
{
    protected $signature = 'finance:relances-echeance';
    protected $description = 'Relance les tranches arrivant à échéance ou en retard';

    public function __construct(
        private ParentNotificationService $parentNotificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tranchesRetard = TrancheFractionnement::where('statut', 'en_attente')
            ->where('date_echeance', '<', now()->toDateString())
            ->with(['plan.eleve', 'plan.facture'])
            ->get();

        foreach ($tranchesRetard as $tranche) {
            $eleve = $tranche->plan->eleve;
            if (!$eleve) continue;

            $this->parentNotificationService->notifier(
                eleveId: $eleve->id,
                type: 'plan_paiement',
                titre: "Retard paiement tranche #{$tranche->numero}",
                corps: "La tranche #{$tranche->numero} de {$tranche->montant} DA est en retard. Échéance : {$tranche->date_echeance->format('d/m/Y')}",
                meta: [
                    'plan_id' => $tranche->plan_id,
                    'tranche_numero' => $tranche->numero,
                    'montant' => $tranche->montant,
                    'date_echeance' => $tranche->date_echeance->format('d/m/Y'),
                ]
            );

            $tranche->update(['statut' => 'en_retard']);
        }

        $this->info("Relances envoyées pour " . $tranchesRetard->count() . " tranches en retard.");
        return Command::SUCCESS;
    }
}
