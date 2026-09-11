<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groupes', function (Blueprint $table) {
            if (!Schema::hasColumn('groupes', 'enseignant_id')) {
                $table->uuid('enseignant_id')->nullable()->after('matiere_id');
                $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groupes', function (Blueprint $table) {
            if (Schema::hasColumn('groupes', 'enseignant_id')) {
                $table->dropForeign(['enseignant_id']);
                $table->dropColumn('enseignant_id');
            }
        });
    }
};
