<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('categorie');

            $table->string('libelle', 200);
            $table->decimal('montant', 12, 2);
            $table->date('date_depense');
            $table->integer('mois');
            $table->integer('annee');
            $table->string('fournisseur', 150)->nullable();
            $table->string('numero_facture_ext', 100)->nullable();
            $table->string('justificatif_url', 500)->nullable();
            $table->string('mode_paiement')->default('cash');
            $table->string('statut')->default('validee');
            $table->uuid('saisie_par')->nullable();
            $table->uuid('validee_par')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('budget_previsionnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->integer('annee');
            $table->integer('mois')->nullable();
            $table->string('categorie');
            $table->decimal('montant_prevu', 12, 2);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'annee', 'mois', 'categorie'], 'budget_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_previsionnel');
        Schema::dropIfExists('depenses');
    }
};
