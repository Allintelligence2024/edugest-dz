<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 — Audit du Row Level Security PostgreSQL.
 *
 * La migration RLS applique ses politiques sous condition (`if driver !==
 * pgsql return` puis un test `tableHasColumn`). Résultat : la couverture
 * réelle était inconnue — une table pouvait silencieusement échapper au RLS
 * sans que personne ne s'en aperçoive.
 *
 * Cette commande rend la couverture observable et vérifiable en CI.
 */
class RlsStatusCommand extends Command
{
    protected $signature = 'rls:status {--json : Sortie machine} {--strict : Code de sortie non nul si une table est non protégée}';

    protected $description = 'Affiche l\'état du Row Level Security PostgreSQL table par table';

    /**
     * Tables devant impérativement être protégées par RLS.
     *
     * Inventaire exhaustif des tables portant réellement une colonne
     * `tenant_id` dans les migrations (101 tables). Généré par analyse des
     * migrations — toute nouvelle table multi-tenant doit y être ajoutée.
     */
    public const TABLES_ATTENDUES = [
        'absences_enseignants', 'absences_journalieres', 'alertes_surveillance',
        'arrets_bus', 'articles_stock', 'audit_log_exports', 'audit_logs',
        'avis', 'avis_marketplace', 'badges', 'billets', 'bons_commande',
        'breach_declarations', 'budget_previsionnel', 'bulletins',
        'cameras_config', 'campagnes', 'candidats_examen',
        'circuits_transport', 'conges_personnel', 'consentements_rgpd',
        'contrats', 'conversations', 'convocations_parents', 'cours',
        'demandes_rgpd', 'depenses', 'device_tokens', 'devoirs',
        'diagnostics_eleves', 'eleves', 'emprunts_bibliotheque',
        'enseignants', 'entretiens_preventifs', 'evaluations', 'factures',
        'favoris_marketplace', 'feedbacks_pedagogiques', 'field_permissions',
        'google_classroom_connexions', 'google_course_liaisons',
        'google_sync_logs', 'groupes', 'historique_diagnostics',
        'inscriptions', 'inscriptions_cantine', 'interventions_entretien',
        'justificatifs_absence', 'lignes_bon_commande',
        'livres_bibliotheque', 'lms_cours', 'lms_inscriptions',
        'locaux_batiment', 'marketplace_commissions', 'matieres',
        'menus_cantine', 'mouvements_stock', 'mouvements_stock_cuisine',
        'notes', 'notifications', 'notifications_inapp',
        'notifications_parent', 'offres_cours', 'offres_publiques',
        'paiements', 'paies', 'paies_personnel', 'parametres', 'parents',
        'personnel_non_enseignant', 'plans_fractionnement',
        'plans_rattrapage', 'pointage_bus', 'pointage_enseignants',
        'pointage_personnel', 'predictions_echec', 'presences',
        'prestataires_entretien', 'prets_materiel',
        'profils_apprentissage', 'profils_marketplace', 'refresh_tokens',
        'repas_journaliers', 'reservations', 'reservations_marketplace',
        'roles', 'salles', 'salles_examen', 'seances', 'security_events',
        'sessions_examen', 'signalements_comportement',
        'signalements_graves_eleves', 'stock_cuisine',
        'super_admin_actions', 'surveillants_examen', 'tenant_modules',
        'tranches_fractionnement', 'transport_eleves', 'users',
        'whatsapp_messages',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('RLS non applicable : le driver courant n\'est pas PostgreSQL.');

            return self::SUCCESS;
        }

        $etat = $this->collecter();

        if ($this->option('json')) {
            $this->line(json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->afficher($etat);
        }

        $problemes = count($etat['non_protegees']) + count($etat['absentes']);

        if ($this->option('strict') && $problemes > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array{protegees:list<string>,non_protegees:list<string>,absentes:list<string>,details:array} */
    public function collecter(): array
    {
        $protegees = [];
        $nonProtegees = [];
        $absentes = [];
        $details = [];

        foreach (self::TABLES_ATTENDUES as $table) {
            if (!Schema::hasTable($table)) {
                $absentes[] = $table;
                continue;
            }

            // Une table inscrite à l'inventaire SANS colonne tenant_id n'a
            // rien à isoler : elle est ignorée (aligné sur le test de
            // couverture qui filtre hasColumn).
            if (!Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $rlsActif = (bool) DB::selectOne(
                'SELECT relrowsecurity FROM pg_class WHERE relname = ?',
                [$table]
            )?->relrowsecurity;

            $nbPolicies = (int) (DB::selectOne(
                'SELECT COUNT(*) AS n FROM pg_policies WHERE tablename = ?',
                [$table]
            )?->n ?? 0);

            $details[$table] = [
                'rls_actif'  => $rlsActif,
                'nb_policies'=> $nbPolicies,
            ];

            // Une table avec RLS activé mais sans politique bloque tout ;
            // une table sans RLS n'isole rien. Les deux sont des anomalies.
            if ($rlsActif && $nbPolicies > 0) {
                $protegees[] = $table;
            } else {
                $nonProtegees[] = $table;
            }
        }

        return [
            'total_attendu'  => count(self::TABLES_ATTENDUES),
            'protegees'      => $protegees,
            'non_protegees'  => $nonProtegees,
            'absentes'       => $absentes,
            'details'        => $details,
        ];
    }

    private function afficher(array $etat): void
    {
        $this->info(sprintf(
            'RLS : %d/%d table(s) protégée(s)',
            count($etat['protegees']),
            $etat['total_attendu']
        ));

        if ($etat['absentes']) {
            $this->warn('Tables absentes du schéma (migration non jouée ?) :');
            $this->line('  ' . implode(', ', $etat['absentes']));
        }

        if ($etat['non_protegees']) {
            $this->error('Tables SANS protection RLS effective :');

            $this->table(
                ['Table', 'RLS activé', 'Politiques'],
                array_map(
                    fn ($t) => [
                        $t,
                        $etat['details'][$t]['rls_actif'] ? 'oui' : 'NON',
                        $etat['details'][$t]['nb_policies'],
                    ],
                    $etat['non_protegees']
                )
            );

            return;
        }

        if (!$etat['absentes']) {
            $this->info('✅ Toutes les tables attendues sont protégées.');
        }
    }
}
