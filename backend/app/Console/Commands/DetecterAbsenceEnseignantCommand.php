<?php

namespace App\Console\Commands;

use App\Models\{Enseignant, Tenant};
use App\Services\AbsenceEnseignantService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Log, Schema};

class DetecterAbsenceEnseignantCommand extends Command
{
    protected $signature   = 'edugest:detecter-absences-enseignants';
    protected $description = 'Détecte les absences des enseignants via la table pointage_enseignants';

    public function handle(AbsenceEnseignantService $absenceService): int
    {
        if (!Schema::hasTable('pointage_enseignants')) {
            $this->warn('La table pointage_enseignants n\'existe pas. Commande annulée.');
            return self::SUCCESS;
        }

        $today = Carbon::today('Africa/Algiers');

        $tenants = Tenant::where('statut', 'actif')->get();

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            try {
                $this->detecterPourTenant($tenant, $today, $absenceService);
            } catch (\Throwable $e) {
                $this->error("Erreur tenant {$tenant->id}: {$e->getMessage()}");
                Log::warning("DetecterAbsenceEnseignant: erreur tenant {$tenant->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Détection absences enseignants terminée — {$today->format('d/m/Y')}");
        return self::SUCCESS;
    }

    private function detecterPourTenant(Tenant $tenant, Carbon $date, AbsenceEnseignantService $absenceService): void
    {
        $enseignants = Enseignant::where('tenant_id', $tenant->id)->get();

        foreach ($enseignants as $enseignant) {
            if (!$enseignant->user_id) continue;

            $seancesPrevues = DB::table('seances as s')
                ->join('cours as c', 's.cours_id', '=', 'c.id')
                ->where('c.tenant_id', $tenant->id)
                ->where('c.enseignant_id', $enseignant->id)
                ->where('s.date_seance', $date->toDateString())
                ->where('s.statut', '!=', 'annulée')
                ->count();

            if ($seancesPrevues === 0) continue;

            try {
                $pointage = DB::table('pointage_enseignants')
                    ->where('tenant_id', $tenant->id)
                    ->where('enseignant_id', $enseignant->id)
                    ->where('date', $date->toDateString())
                    ->first();
            } catch (\Throwable) {
                continue;
            }

            if ($pointage) continue;

            try {
                $absenceService->signalerAbsence(
                    enseignantUserId: $enseignant->user_id,
                    dateAbsence: $date->toDateString(),
                    motif: 'Absence détectée automatiquement (pas de pointage)',
                    tenantId: $tenant->id,
                );

                $this->line("  Absence détectée : {$enseignant->user_id} le {$date->format('d/m/Y')}");
            } catch (\Throwable $e) {
                $this->error("  Échec détection {$enseignant->user_id}: {$e->getMessage()}");
            }
        }
    }
}
