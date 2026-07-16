<?php

namespace App\Console\Commands;

use App\Models\Facture;
use App\Models\Tenant;
use App\Services\NotificationInAppService;
use App\Services\Sms\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RelancesEcheanceCommand extends Command
{
    protected $signature = 'edugest:relances-echeance
        {--dry-run : Afficher les relances sans envoyer}
        {--tenant-id= : Traiter un seul tenant}';

    protected $description = 'Envoyer relances SMS/notification pour factures dont l\'échéance est dépassée';

    private const NIVEAUX = [
        ['max_jours' => 3,   'niveau' => 'douce',            'titre' => 'Rappel de paiement'],
        ['max_jours' => 7,   'niveau' => 'standard',         'titre' => 'Relance standard'],
        ['max_jours' => 15,  'niveau' => 'urgente',          'titre' => 'Relance urgente'],
        ['max_jours' => 999, 'niveau' => 'mise_en_demeure',  'titre' => 'Mise en demeure'],
    ];

    public function handle(
        SmsService $sms,
        NotificationInAppService $notifInApp
    ): int {
        $dryRun  = $this->option('dry-run');
        $tenantId = $this->option('tenant-id');

        if ($dryRun) {
            $this->info('=== MODE DRY-RUN — Aucun SMS/notification envoyé ===');
        }

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        $totalRelances = 0;
        $totalErreurs  = 0;

        foreach ($tenants as $tenant) {
            config(['tenant.current_id' => $tenant->id]);

            $factures = Facture::with('eleve.parents')
                ->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->where('date_echeance', '<', today())
                ->get();

            foreach ($factures as $facture) {
                $joursRetard = today()->diffInDays($facture->date_echeance);
                $niveauInfo  = $this->determinerNiveau($joursRetard);
                $eleve       = $facture->eleve;

                if (!$eleve) {
                    $totalErreurs++;
                    continue;
                }

                $this->line(
                    "  Facture {$facture->numero_facture} | " .
                    "Élève: {$eleve->nom_complet} | " .
                    "J+{$joursRetard} | Niveau: {$niveauInfo['niveau']}"
                );

                if (!$dryRun) {
                    try {
                        $this->envoyerSMS($sms, $facture, $eleve, $niveauInfo, $joursRetard);
                        $totalRelances++;
                    } catch (\Throwable $e) {
                        $totalErreurs++;
                        Log::warning('[RelancesEcheance] SMS échoué', [
                            'facture_id' => $facture->id,
                            'erreur'     => $e->getMessage(),
                        ]);
                    }

                    try {
                        $this->notifierDirecteur(
                            $notifInApp, $tenant, $facture, $eleve, $niveauInfo, $joursRetard
                        );
                    } catch (\Throwable $e) {
                        Log::warning('[RelancesEcheance] Notification in-app échouée', [
                            'facture_id' => $facture->id,
                            'erreur'     => $e->getMessage(),
                        ]);
                    }

                    if ($facture->statut === 'émise' && $joursRetard > 0) {
                        $facture->update(['statut' => 'en_retard']);
                    }
                } else {
                    $totalRelances++;
                }
            }

            $this->info("Tenant {$tenant->nom}: {$factures->count()} factures traitées");
        }

        $this->info("Terminé. Relances: {$totalRelances}, Erreurs: {$totalErreurs}");
        return Command::SUCCESS;
    }

    private function determinerNiveau(int $joursRetard): array
    {
        foreach (self::NIVEAUX as $config) {
            if ($joursRetard <= $config['max_jours']) {
                return $config;
            }
        }
        return end(self::NIVEAUX);
    }

    private function envoyerSMS(
        SmsService $sms,
        Facture $facture,
        $eleve,
        array $niveauInfo,
        int $joursRetard
    ): void {
        $parents = $eleve->parents ?? collect();

        foreach ($parents as $parent) {
            $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
            if (!$tel) continue;

            $montant = number_format($facture->total_ttc, 2, ',', ' ');
            $msg = "EduGest DZ — {$niveauInfo['titre']}\n" .
                   "Facture {$facture->numero_facture}: {$montant} DA\n" .
                   "Échéance dépassée depuis {$joursRetard} jour(s).\n" .
                   "Merci de régulariser votre situation.";

            $sms->send($tel, $msg);
        }
    }

    private function notifierDirecteur(
        NotificationInAppService $notifInApp,
        Tenant $tenant,
        Facture $facture,
        $eleve,
        array $niveauInfo,
        int $joursRetard
    ): void {
        $montant = number_format($facture->total_ttc, 2, ',', ' ');

        $notifInApp->creerPourRole(
            tenantId: $tenant->id,
            role: 'directeur',
            type: 'facture_relance',
            titre: "{$niveauInfo['titre']} — Facture {$facture->numero_facture}",
            corps: "Facture de {$montant} DA pour {$eleve->nom_complet} " .
                   "en retard de {$joursRetard} jour(s). Échéance: " .
                   "{$facture->date_echeance->format('d/m/Y')}.",
            meta: [
                'facture_id'     => $facture->id,
                'eleve_id'       => $eleve->id,
                'jours_retard'   => $joursRetard,
                'niveau'         => $niveauInfo['niveau'],
                'action_url'     => "/factures/{$facture->id}",
                'icone'          => 'facture',
            ],
            urgence: $joursRetard >= 15,
        );
    }
}
