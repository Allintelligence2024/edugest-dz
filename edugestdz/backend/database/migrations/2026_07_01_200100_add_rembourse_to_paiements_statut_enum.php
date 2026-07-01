<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte CHECK
        DB::statement("ALTER TABLE paiements DROP CONSTRAINT IF EXISTS paiements_statut_check");

        // Recréer avec 'remboursé' ajouté
        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_statut_check CHECK (statut IN ('confirmé','annulé','en_attente','remboursé'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE paiements DROP CONSTRAINT IF EXISTS paiements_statut_check");
        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_statut_check CHECK (statut IN ('confirmé','annulé','en_attente'))");
    }
};
