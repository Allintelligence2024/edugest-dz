<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Services\Sms\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmsAbsentsCommand extends Command
{
    protected $signature   = 'edugest:sms-absents {--date= : Date au format Y-m-d (défaut: aujourd\'hui)}';
    protected $description = 'Envoyer SMS aux parents des élèves absents ce matin';

    public function handle(SmsService $sms): int
    {
        $date = $this->option('date') ?? today()->format('Y-m-d');
        $this->info("SMS absents pour le {$date}");

        $absences = AbsenceJournaliere::with(['eleve.parents'])
            ->whereDate('date_absence', $date)
            ->where('sms_parent_envoye', false)
            ->get();

        if ($absences->isEmpty()) {
            $this->info('Aucune absence à notifier.');
            return Command::SUCCESS;
        }

        $envoyes = 0;
        foreach ($absences as $absence) {
            $eleve = $absence->eleve;
            if (! $eleve) continue;

            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if (! $tel) continue;

                $msg = "EduGest: Votre enfant {$eleve->prenom} {$eleve->nom} est "
                     . "absent(e) ce jour ({$date}). Contactez l'établissement si nécessaire.";

                try {
                    $sms->send($tel, $msg);
                    $envoyes++;
                } catch (\Throwable $e) {
                    Log::warning("SMS absent échoué pour élève {$eleve->id}: " . $e->getMessage());
                }
            }

            $absence->update(['sms_parent_envoye' => true, 'sms_envoye_at' => now()]);
        }

        $this->info("{$envoyes} SMS envoyés pour {$absences->count()} absences.");
        Log::info("SmsAbsents: {$envoyes} SMS envoyés, date={$date}");

        return Command::SUCCESS;
    }
}
