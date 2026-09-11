<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'onboarding_etape')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->integer('onboarding_etape')->default(0)->after('statut');
                $table->boolean('onboarding_complete')->default(false)->after('onboarding_etape');
                $table->timestamp('onboarding_complete_le')->nullable()->after('onboarding_complete');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'onboarding_etape')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn(['onboarding_etape', 'onboarding_complete', 'onboarding_complete_le']);
            });
        }
    }
};
