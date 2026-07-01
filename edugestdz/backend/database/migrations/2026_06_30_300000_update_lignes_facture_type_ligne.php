<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL : modifier le type ENUM via contrainte CHECK
        // Supprimer l'ancienne contrainte et en créer une nouvelle
        DB::statement("ALTER TABLE lignes_facture DROP CONSTRAINT IF EXISTS lignes_facture_type_ligne_check");
        DB::statement("ALTER TABLE lignes_facture ADD CONSTRAINT lignes_facture_type_ligne_check
            CHECK (type_ligne IN ('cours', 'transport', 'cantine', 'inscription', 'autre'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lignes_facture DROP CONSTRAINT IF EXISTS lignes_facture_type_ligne_check");
        DB::statement("ALTER TABLE lignes_facture ADD CONSTRAINT lignes_facture_type_ligne_check
            CHECK (type_ligne IN ('cours', 'inscription', 'autre'))");
    }
};
