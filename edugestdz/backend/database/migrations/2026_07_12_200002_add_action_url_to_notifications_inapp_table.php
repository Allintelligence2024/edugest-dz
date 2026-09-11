<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications_inapp')) {
            return;
        }

        Schema::table('notifications_inapp', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications_inapp', 'action_url')) {
                $table->string('action_url', 500)->nullable()->after('lien');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications_inapp')) {
            return;
        }

        Schema::table('notifications_inapp', function (Blueprint $table) {
            if (Schema::hasColumn('notifications_inapp', 'action_url')) {
                $table->dropColumn('action_url');
            }
        });
    }
};
