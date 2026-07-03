<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facture;
use App\Services\Sms\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RelancesImpayesCommand extends Command
{
    protected $signature   = 'edugest:relances-impayes';
    protected $description = 'Envoyer relances SMS/notification aux parents avec factures impayées (J+1/J+3/J+7/J+15)';

    private array $paliers = [1, 3, 7, 15];

    public function handle(SmsService $sms): int
    {
        $this->info('Relances impayés...');
        $total = 0;

        foreach ($this->paliers as $jours) {
            $dateEcheance = today()->subDays($jours);

            $factures = Facture::with(['eleve.parents'])
                ->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->whereDate('date_echeance', $dateEcheance->format('Y-m-d'))
                ->get();

            foreach ($factures as $facture) {
                $eleve = $facture->eleve;
                if (! $eleve) continue;

                $facture->update(['statut' => 'en_retard']);

                foreach ($eleve->parents ?? [] as $parent) {
                    $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                    if (! $tel) continue;

                    $msg = "EduGest: Facture {$facture->numero} de {$facture->total_ttc} DA "
                         . "est impayée depuis {$jours} jour(s). Merci de régulariser.";

                    try {
                        $sms->send($tel, $msg);
                        $total++;
                    } catch (\Throwable $e) {
                        Log::warning("Relance SMS échouée facture {$facture->id}: " . $e->getMessage());
                    }
                }
            }

            $this->line("J+{$jours}: {$factures->count()} factures relancées");
        }

        $this->info("{$total} SMS de relance envoyés.");
        return Command::SUCCESS;
    }
}
