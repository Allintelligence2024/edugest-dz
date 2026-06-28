<?php
namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PaieService;
use Illuminate\Console\Command;

class CalculerPaiesMensuelles extends Command
{
    protected $signature   = 'edugest:calculer-paies {--mois=} {--annee=}';
    protected $description = 'Calculer les paies du mois pour tous les enseignants actifs';

    public function __construct(private PaieService $paieService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mois = (int) ($this->option('mois') ?? now()->month);
        $annee = (int) ($this->option('annee') ?? now()->year);

        $tenants = Tenant::where('statut', 'actif')->get();

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            $this->line("Traitement : {$tenant->nom_etablissement}");

            $resultats = $this->paieService->calculerPaiesMensuelles($mois, $annee);

            foreach ($resultats as $r) {
                $this->line("  {$r['enseignant']} : {$r['net']} DA ({$r['heures']}h)");
            }
        }

        $this->info('✅ Paies calculées');
        return Command::SUCCESS;
    }
}
