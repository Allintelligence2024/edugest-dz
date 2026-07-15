<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\{
    Tenant, Matiere, Enseignant, Eleve, Groupe, Cours,
    Seance, Presence, Note, Facture, Paiement, DiagnosticEleve
};

class VerifyDemoDataCommand extends Command
{
    protected $signature = 'edugest:verify-demo';
    protected $description = 'Vérifier les données de la démo EcoleDemoSeeder';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', 'ecole-demo')->first();

        if (!$tenant) {
            $this->error('❌ Tenant ecole-demo introuvable. Lancé migrate:fresh --seed d\'abord.');
            return 1;
        }

        $this->info("🏫 Vérification des données démo — Tenant: {$tenant->nom_etablissement}");
        $this->newLine();

        $checks = [
            ['Matieres',     Matiere::where('tenant_id', $tenant->id)->count(),       '≥ 5'],
            ['Enseignants',  Enseignant::where('tenant_id', $tenant->id)->count(),    '≥ 5'],
            ['Eleves',       Eleve::where('tenant_id', $tenant->id)->count(),         '≥ 10'],
            ['Groupes',      Groupe::where('tenant_id', $tenant->id)->count(),        '≥ 3'],
            ['Cours',        Cours::where('tenant_id', $tenant->id)->count(),         '≥ 3'],
            ['Seances',      Seance::where('tenant_id', $tenant->id)->count(),        '≥ 10'],
            ['Presences',    Presence::where('tenant_id', $tenant->id)->count(),      '≥ 20'],
            ['Notes',        Note::where('tenant_id', $tenant->id)->count(),          '≥ 10'],
            ['Factures',     Facture::where('tenant_id', $tenant->id)->count(),       '≥ 5'],
            ['Paiements',    Paiement::where('tenant_id', $tenant->id)->count(),      '≥ 3'],
            ['Diagnostics',  DiagnosticEleve::where('tenant_id', $tenant->id)->count(),'≥ 5'],
        ];

        $allPassed = true;

        foreach ($checks as [$label, $count, $expected]) {
            $ok = match(true) {
                str_contains($expected, '≥') => $count >= (int) str_replace('≥ ', '', $expected),
                default => $count > 0,
            };

            $icon = $ok ? '✅' : '❌';
            $this->line("  {$icon} {$label}: {$count} (attendu {$expected})");

            if (!$ok) $allPassed = false;
        }

        $this->newLine();

        $seancesToday = Seance::where('tenant_id', $tenant->id)
            ->where('date_seance', now()->toDateString())
            ->count();
        $this->info("  📅 Séances aujourd'hui: {$seancesToday}");

        $seancesPast = Seance::where('tenant_id', $tenant->id)
            ->where('date_seance', '<', now()->toDateString())
            ->count();
        $this->info("  📅 Séances passées: {$seancesPast}");

        $this->newLine();

        if ($allPassed) {
            $this->info('✅ Toutes les vérifications sont passées !');
            return 0;
        } else {
            $this->error('❌ Certaines vérifications ont échoué.');
            return 1;
        }
    }
}
