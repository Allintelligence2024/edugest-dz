<?php
namespace App\Console\Commands;

use App\Jobs\NotifierAbsenceParent;
use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Models\Tenant;
use Illuminate\Console\Command;

class VerifierAbsencesMatin extends Command
{
    protected $signature   = 'absences:verifier-matin';
    protected $description = 'Crée les absences du jour et notifie les parents des élèves non signalés';

    public function handle(): int
    {
        $today = today();
        $heureLimite = '08:30';

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($today, $heureLimite) {
            config(['tenant.current_id' => $tenant->id]);

            $eleves = Eleve::where('statut', 'actif')->get();

            foreach ($eleves as $eleve) {
                $existe = AbsenceJournaliere::where('eleve_id', $eleve->id)
                    ->where('date_absence', $today)
                    ->exists();

                if ($existe) continue;

                $absence = AbsenceJournaliere::create([
                    'tenant_id'   => $tenant->id,
                    'eleve_id'    => $eleve->id,
                    'date_absence'=> $today,
                    'statut'      => 'absent',
                    'signale_par' => 'auto',
                ]);

                NotifierAbsenceParent::dispatch($absence->id)->onQueue('notifications');
            }
        });

        $this->info('Vérification absences matin terminée : ' . $today->format('d/m/Y'));
        return self::SUCCESS;
    }
}
