<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('langue', 5)->default('fr')->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'quotas')) {
                $table->json('quotas')->nullable()->after('settings');
            }
            if (!Schema::hasColumn('tenants', 'plan_abonnement')) {
                $table->string('plan_abonnement', 50)->default('gratuit')->change();
            } else {
                $table->string('plan_abonnement', 50)->default('gratuit')->change();
            }
        });

        if (!Schema::hasTable('super_admin_actions')) {
            Schema::create('super_admin_actions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('super_admin_id');
                $table->uuid('tenant_id')->nullable();
                $table->string('action', 100);
                $table->json('details')->nullable();
                $table->timestamps();

                $table->foreign('super_admin_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('super_admin_id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_actions');
    }
};
