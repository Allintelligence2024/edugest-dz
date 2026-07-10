<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index GIN sur les colonnes jsonb — PostgreSQL uniquement.
 *
 * Index GIN (Generalized Inverted Index) pour les colonnes jsonb :
 *   - Permet les requêtes @> (contient), <@ (est contenu dans)
 *   - Permet ? (clé existe), ?| (une des clés), ?& (toutes les clés)
 *   - Essentiel pour les recherches dans security_events et audit_chain
 *
 * Ces index n'existent pas dans SQLite (une raison de plus pour PostgreSQL pur).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index GIN sur security_events.details
        // Permet : WHERE details @> '{"user_id": "xxx"}' → rapide même avec 1M+ lignes
        if (Schema::hasTable('security_events') && Schema::hasColumn('security_events', 'details')) {
            try {
                DB::statement('
                    CREATE INDEX IF NOT EXISTS idx_gin_security_events_details
                    ON security_events USING GIN (details)
                ');
            } catch (\Throwable) {}
        }

        // Index GIN sur request_risk_scores.facteurs
        // Permet de chercher tous les events avec un facteur spécifique
        if (Schema::hasTable('request_risk_scores') && Schema::hasColumn('request_risk_scores', 'facteurs')) {
            try {
                DB::statement('
                    CREATE INDEX IF NOT EXISTS idx_gin_risk_scores_facteurs
                    ON request_risk_scores USING GIN (facteurs)
                ');
            } catch (\Throwable) {}
        }

        // Index GIN sur audit_chain.payload
        // Permet la recherche dans les payloads d'audit sans scan complet
        if (Schema::hasTable('audit_chain') && Schema::hasColumn('audit_chain', 'payload')) {
            try {
                DB::statement('
                    CREATE INDEX IF NOT EXISTS idx_gin_audit_chain_payload
                    ON audit_chain USING GIN (payload)
                ');
            } catch (\Throwable) {}
        }

        // Index GIN sur honeypot_triggers.donnees
        if (Schema::hasTable('honeypot_triggers') && Schema::hasColumn('honeypot_triggers', 'donnees')) {
            try {
                DB::statement('
                    CREATE INDEX IF NOT EXISTS idx_gin_honeypot_donnees
                    ON honeypot_triggers USING GIN (donnees)
                ');
            } catch (\Throwable) {}
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_gin_security_events_details',
            'idx_gin_risk_scores_facteurs',
            'idx_gin_audit_chain_payload',
            'idx_gin_honeypot_donnees',
        ];
        foreach ($indexes as $idx) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$idx}");
            } catch (\Throwable) {}
        }
    }
};
