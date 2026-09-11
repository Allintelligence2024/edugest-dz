<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signalements_graves_eleves')) {
            Schema::create('signalements_graves_eleves', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');
                $table->uuid('concerne_id')->nullable();
                $table->string('type_incident', 50);
                $table->string('gravite', 20);
                $table->text('description');
                $table->date('date_incident');
                $table->text('temoins')->nullable();
                $table->string('statut', 30)->default('soumis');
                $table->uuid('traite_par')->nullable();
                $table->text('commentaire_admin')->nullable();
                $table->timestamp('traite_le')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'statut'], 'idx_sig_grave_statut');
                $table->index(['eleve_id'], 'idx_sig_grave_eleve');
                $table->index(['concerne_id'], 'idx_sig_grave_concerne');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements_graves_eleves');
    }
};
