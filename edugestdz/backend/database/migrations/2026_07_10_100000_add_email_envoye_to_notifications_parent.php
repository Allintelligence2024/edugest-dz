<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications_parent')) {
            Schema::table('notifications_parent', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications_parent', 'email_envoye')) {
                    $table->boolean('email_envoye')->default(false)->after('sms_envoye');
                }
                if (!Schema::hasColumn('notifications_parent', 'email_parent')) {
                    $table->string('email_parent', 150)->nullable()->after('email_envoye');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications_parent')) {
            Schema::table('notifications_parent', function (Blueprint $table) {
                $table->dropColumnIfExists('email_envoye');
                $table->dropColumnIfExists('email_parent');
            });
        }
    }
};
