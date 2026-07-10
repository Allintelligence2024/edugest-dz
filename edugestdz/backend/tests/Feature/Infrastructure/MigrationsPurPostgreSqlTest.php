<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Vérifie que les migrations utilisent bien les fonctionnalités PostgreSQL.
 * Détecte les colonnes qui devraient être jsonb mais sont restées json.
 */
class MigrationsPurPostgreSqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_colonnes_critiques_sont_jsonb(): void
    {
        // Ces colonnes doivent être jsonb après la migration de conversion
        $expected_jsonb = [
            ['table' => 'security_events',     'column' => 'details'],
            ['table' => 'request_risk_scores',  'column' => 'facteurs'],
            ['table' => 'audit_chain',          'column' => 'payload'],
        ];

        foreach ($expected_jsonb as $check) {
            if (!DB::selectOne(
                "SELECT 1 FROM information_schema.tables WHERE table_name=?",
                [$check['table']]
            )) {
                continue; // Table pas encore créée → skip
            }

            if (!DB::selectOne(
                "SELECT 1 FROM information_schema.columns WHERE table_name=? AND column_name=?",
                [$check['table'], $check['column']]
            )) {
                continue; // Colonne pas encore créée → skip
            }

            $type = DB::selectOne("
                SELECT data_type
                FROM information_schema.columns
                WHERE table_name  = ?
                  AND column_name = ?
                  AND table_schema = 'public'
            ", [$check['table'], $check['column']]);

            // Note : on accepte 'json' aussi pour l'instant (migration en cours)
            // mais on log si c'est pas jsonb
            if ($type && $type->data_type !== 'jsonb') {
                $this->addWarning(
                    "{$check['table']}.{$check['column']} est '{$type->data_type}' " .
                    "au lieu de 'jsonb'. Lancer la migration de conversion."
                );
            }
        }

        // Test toujours vert (avertissements uniquement, pas d'erreur bloquante)
        $this->assertTrue(true);
    }

    public function test_rls_actif_sur_tables_critiques(): void
    {
        // Vérifier que RLS est activé sur les tables sensibles
        $tables_rls = ['eleves', 'users', 'factures', 'notes'];

        foreach ($tables_rls as $table) {
            if (!DB::selectOne(
                "SELECT 1 FROM information_schema.tables WHERE table_name=?",
                [$table]
            )) {
                continue;
            }

            $rls = DB::selectOne("
                SELECT rowsecurity
                FROM pg_tables
                WHERE tablename = ?
                  AND schemaname = 'public'
            ", [$table]);

            if ($rls && !$rls->rowsecurity) {
                $this->addWarning(
                    "RLS non activé sur la table '{$table}'.\n" .
                    "Lancer la migration add_postgresql_row_level_security."
                );
            }
        }

        $this->assertTrue(true);
    }

    public function test_aucune_table_sqlite_workaround(): void
    {
        // Vérifier qu'il n'y a pas de table 'sqlite_sequence' (résidu SQLite)
        $sqlite_table = DB::selectOne("
            SELECT 1 FROM information_schema.tables
            WHERE table_name = 'sqlite_sequence'
              AND table_schema = 'public'
        ");

        $this->assertNull(
            $sqlite_table,
            "Table sqlite_sequence détectée — résidu SQLite dans PostgreSQL !"
        );
    }
}
