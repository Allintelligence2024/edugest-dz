<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('periode');
            $table->string('type_export');
            $table->integer('nb_entrees')->default(0);
            $table->text('hash_sha256');
            $table->text('signature');
            $table->string('fichier_chemin')->nullable();
            $table->boolean('integrite_ok')->default(true);
            $table->timestamp('exporte_le')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'exporte_le'], 'idx_audit_export_tenant');
            $table->index(['type_export', 'exporte_le'], 'idx_audit_export_type');
        });

        Schema::create('breach_declarations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('type_incident');
            $table->string('severite');
            $table->text('description');
            $table->json('donnees_affectees')->default('[]');
            $table->integer('nb_personnes_affectees')->default(0);
            $table->timestamp('detecte_le');
            $table->timestamp('contenu_le')->nullable();
            $table->timestamp('notifie_clients_le')->nullable();
            $table->timestamp('notifie_anpdp_le')->nullable();
            $table->string('statut')->default('ouvert');
            $table->text('actions_prises')->nullable();
            $table->text('lecons_apprises')->nullable();
            $table->uuid('declare_par');
            $table->timestamps();

            $table->index(['statut', 'detecte_le'], 'idx_breach_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breach_declarations');
        Schema::dropIfExists('audit_log_exports');
    }
};
