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
        $tables = [
            'eleves', 'users', 'groupes', 'cours', 'seances',
            'presences', 'evaluations', 'notes', 'bulletins',
            'factures', 'paiements', 'absences_journalieres', 'billets',
            'enseignants', 'contrats', 'personnel_non_enseignant', 'paies',
            'circuits_transport', 'transport_eleves', 'pointage_bus',
            'menus_cantine', 'inscriptions_cantine', 'repas_journaliers',
            'articles_stock', 'mouvements_stock', 'prets_stock',
            'bons_commande', 'depenses', 'budgets_previsionnels',
            'locaux', 'interventions_entretien', 'entretiens_preventifs',
            'cameras_config', 'alertes_surveillance',
            'lms_cours', 'lms_inscriptions',
            'tenant_modules', 'whatsapp_messages',
            'diagnostics_eleves', 'plans_rattrapage', 'convocations_parents',
            'signalements_comportement', 'notifications_parent',
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
                        tenant_id = current_setting('app.current_tenant_id', true)::uuid
                        OR current_setting('app.current_tenant_id', true) IS NULL
                        OR current_setting('app.current_tenant_id', true) = ''
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
        $tables = [
            'eleves', 'users', 'groupes', 'cours', 'seances',
            'presences', 'evaluations', 'notes', 'bulletins',
            'factures', 'paiements', 'absences_journalieres', 'billets',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            } catch (\Throwable $e) {}
        }
    }
};
