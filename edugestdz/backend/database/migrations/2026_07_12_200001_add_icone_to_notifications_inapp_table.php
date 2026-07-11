<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications_inapp') && !Schema::hasColumn('notifications_inapp', 'icone')) {
            Schema::table('notifications_inapp', function (Blueprint $table) {
                $table->string('icone')->nullable()->after('lu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications_inapp') && Schema::hasColumn('notifications_inapp', 'icone')) {
            Schema::table('notifications_inapp', function (Blueprint $table) {
                $table->dropColumn('icone');
            });
        }
    }
};
