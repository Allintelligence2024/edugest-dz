<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion json → jsonb pour les colonnes stratégiques.
 *
 * POURQUOI jsonb EST SUPÉRIEUR À json :
 *   - Stockage binaire décompressé → lecture plus rapide
 *   - Indexation GIN possible → SELECT * WHERE data @> '{"key": "val"}'
 *   - Opérateurs @>, <@, ?, ?|, ?& disponibles
 *   - Déduplication des clés automatique
 *   - Ordre des clés normalisé → comparaisons possibles
 *
 * COLONNES CONVERTIES :
 *   - security_events.details → recherche par type d'événement
 *   - request_risk_scores.facteurs → analyse des facteurs de risque
 *   - audit_chain.payload → intégrité vérifiable
 *   - kill_switch_votes.payload → auditabilité
 *   - honeypot_triggers.donnees → analyse forensique
 *
 * SÉCURITÉ : Cette migration est idempotente (ignore si déjà jsonb).
 */
return new class extends Migration
{
    // Tables et colonnes à convertir
    private const CONVERSIONS = [
        'security_events'      => ['details'],
        'request_risk_scores'  => ['facteurs'],
        'audit_chain'          => ['payload'],
        'kill_switch_votes'    => ['payload'],
        'honeypot_triggers'    => ['donnees'],
        'breach_declarations'  => ['donnees_affectees'],
        'notifications_inapp'  => [],
    ];

    public function up(): void
    {
        // Cette migration est PostgreSQL-only — pas de guard nécessaire
        // car config/database.php ne contient plus que pgsql

        foreach (self::CONVERSIONS as $table => $columns) {
            if (!Schema::hasTable($table)) continue;

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) continue;

                try {
                    // Vérifier si déjà jsonb
                    $type = DB::selectOne("
                        SELECT data_type
                        FROM information_schema.columns
                        WHERE table_name = ?
                          AND column_name = ?
                          AND table_schema = 'public'
                    ", [$table, $column]);

                    if ($type && $type->data_type === 'jsonb') {
                        // Déjà en jsonb → skip
                        continue;
                    }

                    // Convertir json → jsonb
                    DB::statement("
                        ALTER TABLE {$table}
                        ALTER COLUMN {$column} TYPE jsonb
                        USING {$column}::text::jsonb
                    ");

                    \Illuminate\Support\Facades\Log::info(
                        "Migration jsonb: {$table}.{$column} converti json→jsonb"
                    );

                } catch (\Throwable $e) {
                    // Log mais ne pas bloquer — la migration est best-effort
                    \Illuminate\Support\Facades\Log::warning(
                        "Migration jsonb: impossible de convertir {$table}.{$column}",
                        ['error' => $e->getMessage()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback — jsonb est un sur-ensemble de json
        // Revenir à json ne fait pas sens (perte de fonctionnalités)
    }
};
