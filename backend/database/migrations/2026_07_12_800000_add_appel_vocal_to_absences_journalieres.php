<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absences_journalieres', 'sms_parent_envoye')
            && !Schema::hasColumn('absences_journalieres', 'appel_vocal_envoye')) {
            Schema::table('absences_journalieres', function (Blueprint $table) {
                $table->boolean('appel_vocal_envoye')->default(false);
                $table->timestamp('appel_vocal_envoye_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('absences_journalieres', 'appel_vocal_envoye')) {
            Schema::table('absences_journalieres', function (Blueprint $table) {
                $table->dropColumn(['appel_vocal_envoye', 'appel_vocal_envoye_at']);
            });
        }
    }
};
