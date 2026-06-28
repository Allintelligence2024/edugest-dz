<?php
namespace App\Console\Commands;

use App\Models\{Cours, Tenant};
use App\Services\PlanningService;
use Illuminate\Console\Command;

class GenererSeancesHebdomadaires extends Command
{
    protected $signature   = 'edugest:generer-seances';
    protected $description = 'Générer les séances de la semaine prochaine';

    public function __construct(private PlanningService $planning)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = Tenant::where('statut', 'actif')->get();
        $total   = 0;

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            $cours = Cours::where('statut', 'actif')->get();

            foreach ($cours as $c) {
                $total += $this->planning->genererSeances($c);
            }

            $this->line("{$tenant->nom_etablissement} : {$cours->count()} cours traités");
        }

        $this->info("✅ {$total} séances générées");
        return Command::SUCCESS;
    }
}
