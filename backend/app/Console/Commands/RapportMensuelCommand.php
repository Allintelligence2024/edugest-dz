<?php

namespace App\Console\Commands;

use App\Models\{Tenant, User};
use App\Services\RapportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Mail, Log};

class RapportMensuelCommand extends Command
{
    protected $signature   = 'edugest:rapport-mensuel {mois?} {annee?}';
    protected $description = 'Génère et envoie le rapport mensuel au directeur';

    public function handle(RapportService $rapportService): int
    {
        $mois   = $this->argument('mois') ?? (int) Carbon::now('Africa/Algiers')->subMonth()->format('m');
        $annee  = $this->argument('annee') ?? (int) Carbon::now('Africa/Algiers')->subMonth()->format('Y');

        $tenants = Tenant::where('statut', 'actif')->get();

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            try {
                $this->genererRapport($tenant, (int) $mois, (int) $annee, $rapportService);
            } catch (\Throwable $e) {
                $this->error("Erreur rapport tenant {$tenant->id}: {$e->getMessage()}");
                Log::warning("RapportMensuel: erreur tenant {$tenant->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Rapport mensuel {$mois}/{$annee} terminé.");
        return self::SUCCESS;
    }

    private function genererRapport(Tenant $tenant, int $mois, int $annee, RapportService $rapportService): void
    {
        $pdf = $rapportService->rapportAbsencesMensuel($mois, $annee);

        $directeurs = User::where('tenant_id', $tenant->id)
            ->whereHas('role', fn($q) => $q->where('nom', 'admin'))
            ->get();

        if ($directeurs->isEmpty()) {
            $this->warn("  Aucun directeur trouvé pour tenant {$tenant->id}");
            return;
        }

        $moisLabel = Carbon::create($annee, $mois, 1)->translatedFormat('F Y');
        $filename  = "rapport-absences-{$mois}-{$annee}.pdf";

        foreach ($directeurs as $directeur) {
            if (!$directeur->email) continue;

            try {
                Mail::send([], [], function ($message) use ($directeur, $pdf, $filename, $moisLabel) {
                    $message->to($directeur->email)
                        ->subject("EduGest DZ — Rapport mensuel des absences — {$moisLabel}")
                        ->from(
                            config('mail.from.address', 'noreply@edugestdz.dz'),
                            config('mail.from.name', 'EduGest DZ')
                        )
                        ->attachData($pdf->output(), $filename);
                });

                $this->line("  Rapport envoyé à {$directeur->email}");
            } catch (\Throwable $e) {
                $this->error("  Échec envoi à {$directeur->email}: {$e->getMessage()}");
            }
        }
    }
}
