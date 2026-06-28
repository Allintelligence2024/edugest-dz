<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campagnes')) {
            Schema::create('campagnes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('titre', 200);
                $table->text('message');
                $table->jsonb('canaux');
                $table->jsonb('filtres')->nullable();
                $table->jsonb('destinataires')->nullable();
                $table->integer('nb_destinataires')->default(0);
                $table->integer('nb_envoyes')->default(0);
                $table->integer('nb_echecs')->default(0);
                $table->string('statut')->default('brouillon');
                $table->timestamp('programmee_le')->nullable();
                $table->timestamp('envoyee_le')->nullable();
                $table->uuid('cree_par');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index('tenant_id');
                $table->index('statut');
            });

            Schema::create('campagne_destinataires', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campagne_id');
                $table->uuid('destinataire_id');
                $table->string('canal')->nullable();
                $table->string('statut')->default('en_attente');
                $table->text('erreur')->nullable();
                $table->timestamp('envoye_le')->nullable();

                $table->foreign('campagne_id')->references('id')->on('campagnes')->onDelete('cascade');
                $table->index('campagne_id');
                $table->index('statut');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campagne_destinataires');
        Schema::dropIfExists('campagnes');
    }
};
