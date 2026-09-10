<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            if (!Schema::hasColumn('bulletins', 'statut_pdf')) {
                $table->string('statut_pdf', 20)->default('en_attente')->after('fichier_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            if (Schema::hasColumn('bulletins', 'statut_pdf')) {
                $table->dropColumn('statut_pdf');
            }
        });
    }
};
