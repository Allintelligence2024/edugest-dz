<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'marketplace_featured')) {
                $table->boolean('marketplace_featured')->default(false)->after('statut');
            }
            if (!Schema::hasColumn('tenants', 'type_etablissement')) {
                $table->string('type_etablissement', 50)->nullable()->after('marketplace_featured');
            }
            if (!Schema::hasColumn('tenants', 'description')) {
                $table->text('description')->nullable()->after('nom');
            }
            if (!Schema::hasColumn('tenants', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumnIfExists('marketplace_featured');
            $table->dropColumnIfExists('type_etablissement');
        });
    }
};
