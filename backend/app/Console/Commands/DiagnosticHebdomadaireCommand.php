<?php

namespace App\Console\Commands;

use App\Services\DiagnosticService;
use App\Services\SmsService;
use App\Models\DiagnosticEleve;
use App\Models\ConvocationParent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnosticHebdomadaireCommand extends Command
{
    protected $signature   = 'edugest:diagnostic-hebdomadaire {--tenant= : Analyser un tenant spécifique}';
    protected $description = 'Analyse hebdomadaire du niveau de tous les élèves + envoi convocations';

    public function handle(DiagnosticService $diagnostic, SmsService $sms): int
    {
        $tenantId = $this->option('tenant');
        $this->info('🔍 Analyse diagnostique hebdomadaire...');

        $resultats = $diagnostic->analyserTousLesEleves($tenantId);
        $this->info("✅ Analyses : {$resultats['total']} élèves");
        $this->info("🚨 Critiques : {$resultats['critiques']}");
        $this->info("🔴 Dangers   : {$resultats['dangers']}");
        $this->info("⭐ Excellents: {$resultats['excellents']}");

        $aConvoquer = DiagnosticEleve::where('convocation_requise', true)
            ->whereDoesntHave('convocations', fn($q) =>
                $q->where('statut', '!=', 'annulée')
                  ->where('created_at', '>=', now()->subWeeks(4))
            )
            ->with('eleve.parents')
            ->get();

        $convoqués = 0;
        foreach ($aConvoquer as $diag) {
            $eleve = $diag->eleve;
            if (!$eleve) continue;

            $motif = $diag->niveau_global === 'critique'
                ? 'niveau_critique'
                : ($diag->nb_absences_mois > 6 ? 'absences_excessives' : 'niveau_critique');

            $msg = "EduGest — Convocation Parent\n\n"
                 . "Établissement : " . config('app.name') . "\n"
                 . "Élève : {$eleve->prenom} {$eleve->nom} ({$eleve->niveau_scolaire})\n"
                 . "Motif : " . ($motif === 'niveau_critique'
                     ? "Niveau académique insuffisant (moyenne: {$diag->moyenne_generale}/20)"
                     : "Absentéisme excessif ({$diag->nb_absences_mois} absences ce mois)") . "\n"
                 . "Action requise : Prendre rendez-vous avec le directeur.\n"
                 . "Contact : " . config('app.contact_phone', "Voir l'établissement");

            $convocation = ConvocationParent::create([
                'tenant_id'  => $eleve->tenant_id,
                'eleve_id'   => $eleve->id,
                'motif'      => $motif,
                'message'    => $msg,
                'canal'      => 'sms',
                'statut'     => 'envoyée',
                'envoyee_le' => now(),
                'cree_par'   => null,
            ]);

            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try { $sms->send($tel, $msg); $convoqués++; } catch (\Throwable) {}
                }
            }
        }

        $this->info("📱 Convocations envoyées : {$convoqués}");

        $excellents = DiagnosticEleve::where('mention_excellence', true)
            ->with('eleve:id,nom,prenom')
            ->get();
        if ($excellents->count() > 0) {
            $this->info("⭐ Élèves excellents ({$excellents->count()}) :");
            foreach ($excellents as $d) {
                $this->line("   → {$d->eleve?->nom_complet} — {$d->moyenne_generale}/20");
            }
        }

        Log::info('Diagnostic hebdomadaire terminé', $resultats);
        return Command::SUCCESS;
    }
}
