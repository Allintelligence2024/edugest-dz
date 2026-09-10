<?php
namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\FacturationService;
use App\Services\Sms\SmsService;
use Illuminate\Console\Command;

class EnvoyerRelancesPaiement extends Command
{
    protected $signature   = 'edugest:relances-paiement {--tenant=}';
    protected $description = 'Envoyer les relances de paiement pour les factures en retard';

    public function handle(SmsService $smsService): int
    {
        $tenants = Tenant::where('statut', 'actif')->get();

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            $this->line("Traitement : {$tenant->nom_etablissement}");

            $factures = \App\Models\Facture::whereIn('statut', ['émise', 'en_retard'])
                ->where('date_echeance', '<', today())
                ->get();

            $this->info("  {$factures->count()} facture(s) en retard");

            $smsEnvoyes = 0;

            foreach ($factures as $facture) {
                $user = User::find($facture->user_id);

                if (!$user) {
                    continue;
                }

                $notification = \App\Models\Notification::create([
                    'tenant_id'  => $tenant->id,
                    'user_id'    => $user->id,
                    'type'       => 'relance',
                    'titre'      => 'Relance de paiement',
                    'message'    => "Votre facture de {$facture->montant} DZD arrivée à échéance le {$facture->date_echeance} est impayée.",
                    'lien'       => config('app.url') . '/factures/' . $facture->id,
                    'est_lu'     => false,
                    'envoye_par' => 'system',
                ]);

                if ($user->telephone) {
                    $result = $smsService->sendRelanceImpaye(
                        $user->telephone,
                        $facture->montant,
                        $facture->date_echeance->format('d/m/Y')
                    );

                    if ($result['success']) {
                        $smsEnvoyes++;
                        $this->line("    SMS envoyé à {$user->telephone} (ID: {$result['messageId']})");
                    } else {
                        $this->warn("    Échec SMS pour {$user->telephone} : {$result['error']}");
                    }
                }
            }

            $this->info("  {$smsEnvoyes} SMS de relance envoyé(s)");
        }

        $this->info('✅ Relances terminées');
        return Command::SUCCESS;
    }
}
