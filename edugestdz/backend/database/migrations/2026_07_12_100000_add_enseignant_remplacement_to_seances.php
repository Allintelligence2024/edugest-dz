<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->uuid('enseignant_remplacement_id')->nullable()->after('motif_annulation');
            $table->foreign('enseignant_remplacement_id')
                  ->references('id')->on('enseignants')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropForeign(['enseignant_remplacement_id']);
            $table->dropColumn('enseignant_remplacement_id');
        });
    }
};
