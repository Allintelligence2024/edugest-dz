<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function tableHasColumn(string $table, string $column): bool
    {
        $result = DB::select("
            SELECT 1 FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);
        return !empty($result);
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Inventaire exhaustif des tables portant une colonne `tenant_id`
        // (101 tables). La politique RLS reste conditionnelle : elle ne filtre
        // que si la session pose `app.current_tenant_id`.
        $tables = [
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

        foreach ($tables as $table) {
            DB::statement("SAVEPOINT rls_savepoint");

            try {
                $exists = DB::select("SELECT 1 FROM information_schema.tables WHERE table_name = ?", [$table]);
                if (empty($exists)) {
                    DB::statement("RELEASE SAVEPOINT rls_savepoint");
                    continue;
                }

                if (!$this->tableHasColumn($table, 'tenant_id')) {
                    DB::statement("RELEASE SAVEPOINT rls_savepoint");
                    continue;
                }

                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
                DB::statement("
                    CREATE POLICY tenant_isolation_policy ON {$table}
                    USING (
                        current_setting('app.current_tenant_id', true) IS NULL
                        OR current_setting('app.current_tenant_id', true) = ''
                        OR tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ");

                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

                DB::statement("RELEASE SAVEPOINT rls_savepoint");

            } catch (\Throwable $e) {
                DB::statement("ROLLBACK TO SAVEPOINT rls_savepoint");
                \Illuminate\Support\Facades\Log::warning(
                    "RLS skip pour {$table}: " . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
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

        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            } catch (\Throwable $e) {}
        }
    }
};
