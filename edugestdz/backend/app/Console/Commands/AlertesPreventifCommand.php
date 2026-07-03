<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EntretienPreventif;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AlertesPreventifCommand extends Command
{
    protected $signature   = 'edugest:alertes-preventif';
    protected $description = 'Alerter les admins sur les entretiens préventifs à échéance';

    public function handle(): int
    {
        $this->info('Vérification entretiens préventifs...');

        $aVenir = EntretienPreventif::where('actif', true)
            ->where('prochaine_echeance', '<=', today()->addDays(7)->format('Y-m-d'))
            ->get();

        if ($aVenir->isEmpty()) {
            $this->info('Aucun entretien préventif urgent.');
            return Command::SUCCESS;
        }

        foreach ($aVenir as $entretien) {
            $echeance = Carbon::parse($entretien->prochaine_echeance);
            $jours    = today()->diffInDays($echeance, false);
            $status   = $jours < 0 ? "EN RETARD de {$jours} jours" : "dans {$jours} jours";

            Log::warning("Entretien préventif #{$entretien->id} — {$entretien->description} — {$status}");
        }

        $this->info("{$aVenir->count()} entretiens préventifs urgents.");
        return Command::SUCCESS;
    }
}
