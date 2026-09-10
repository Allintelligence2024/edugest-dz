<?php

namespace App\Console\Commands;

use App\Models\AbsenceJournaliere;
use App\Models\Tenant;
use App\Services\TwilioVoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppelVocalAbsenceCommand extends Command
{
    protected $signature = 'edugest:appel-vocal-absence
                            {--date= : Date au format Y-m-d}
                            {--dry-run : Simuler sans appeler}
                            {--force : Ignorer le cache d\'idempotence}';

    protected $description = 'Appeler les parents des élèves absents (Twilio Voice TTS)';

    public function handle(TwilioVoiceService $voice): int
    {
        $date = $this->option('date') ?? today()->format('Y-m-d');
        $dryRun = $this->option('dry-run');
        $this->info("Appel vocal absence — {$date}" . ($dryRun ? ' [DRY-RUN]' : ''));

        if ($dryRun) {
            return $this->dryRun($date);
        }

        $cleCache = "appel_vocal_{$date}";
        if (!$this->option('force') && Cache::get($cleCache)) {
            $this->info('Déjà effectué aujourd\'hui. Utiliser --force pour relancer.');
            return Command::SUCCESS;
        }

        $appelles = 0;
        $echecs = 0;

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($date, $voice, &$appelles, &$echecs) {
            config(['tenant.current_id' => $tenant->id]);

            $absences = AbsenceJournaliere::with(['eleve.parents'])
                ->whereDate('date_absence', $date)
                ->where('appel_vocal_envoye', false)
                ->get();

            foreach ($absences as $absence) {
                $eleve = $absence->eleve;
                if (!$eleve) continue;

                $parents = $eleve->parents;
                if ($parents->isEmpty()) {
                    $this->line("  ⚠ Aucun parent pour {$eleve->prenom} {$eleve->nom}");
                    continue;
                }

                foreach ($parents as $parent) {
                    $tel = $parent->telephone_1 ?? $parent->telephone_2 ?? null;
                    if (!$tel) continue;

                    $message = $this->composerMessage($eleve, $date);
                    $result = $voice->appeler($tel, $message);

                    $this->loggerAppelDansAudit($absence, $parent, $result);

                    if ($result['success']) {
                        $appelles++;
                        $this->line("  ✓ Appelé {$parent->prenom} {$parent->nom} ({$tel})");
                    } else {
                        $echecs++;
                        $this->line("  ✗ Échec {$parent->prenom} : {$result['error']}");
                    }
                }

                $absence->update([
                    'appel_vocal_envoye'    => true,
                    'appel_vocal_envoye_at' => now(),
                ]);
            }
        });

        Cache::put($cleCache, true, now()->addDay());
        $this->info("Terminé : {$appelles} appel(s), {$echecs} échec(s)");
        return Command::SUCCESS;
    }

    private function dryRun(string $date): int
    {
        $total = 0;

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($date, &$total) {
            config(['tenant.current_id' => $tenant->id]);
            $total += AbsenceJournaliere::with('eleve.parents')
                ->whereDate('date_absence', $date)
                ->where('appel_vocal_envoye', false)
                ->count();
        });

        $this->info("[DRY-RUN] {$total} absence(s) à traiter — aucun appel passé.");
        return Command::SUCCESS;
    }

    private function composerMessage($eleve, string $date): string
    {
        $dateFormatee = Carbon::parse($date)->translatedFormat('d/m/Y');

        return "Bonjour. Ceci est un appel automatique de l'établissement scolaire. "
             . "Nous vous informons que votre enfant {$eleve->prenom} {$eleve->nom} "
             . "est absent(e) aujourd'hui, le {$dateFormatee}. "
             . "Merci de contacter l'établissement pour justifier cette absence. "
             . "Merci et bonne journée.";
    }

    private function loggerAppelDansAudit($absence, $parent, array $result): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id'         => config('tenant.current_id'),
                'action'            => 'appel_vocal_absence',
                'table_concernee'   => 'absences_journalieres',
                'enregistrement_id' => $absence->id,
                'nouvelles_valeurs' => json_encode([
                    'parent_id' => $parent->id,
                    'telephone' => $parent->telephone_1 ?? null,
                    'success'   => $result['success'],
                    'call_sid'  => $result['call_sid'] ?? null,
                    'error'     => $result['error'] ?? null,
                ]),
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Audit log appel vocal ignoré : ' . $e->getMessage());
        }
    }
}
