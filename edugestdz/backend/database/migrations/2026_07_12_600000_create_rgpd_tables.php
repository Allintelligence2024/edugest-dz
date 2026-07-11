<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('consentements_rgpd')) {
            Schema::create('consentements_rgpd', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('user_id');
                $table->string('type_consentement', 50);
                $table->boolean('accepte')->default(true);
                $table->string('version', 20)->default('1.0');
                $table->string('ip_adresse', 45)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id'], 'idx_consent_tenant_user');
            });
        }

        if (!Schema::hasTable('demandes_rgpd')) {
            Schema::create('demandes_rgpd', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('user_id');
                $table->string('type', 30);
                $table->string('statut', 20)->default('en_cours');
                $table->text('commentaire')->nullable();
                $table->string('fichier_chemin', 500)->nullable();
                $table->timestamp('traite_le')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'statut'], 'idx_rgpd_tenant_statut');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consentements_rgpd');
        Schema::dropIfExists('demandes_rgpd');
    }
};
