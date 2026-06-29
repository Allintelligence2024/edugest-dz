<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->enum('categorie', [
                'salaires_enseignants',
                'salaires_personnel',
                'loyer',
                'electricite_gaz',
                'eau',
                'telephone_internet',
                'fournitures_bureau',
                'fournitures_pedagogiques',
                'maintenance_reparation',
                'assurance',
                'publicite_marketing',
                'transport',
                'cantine_restauration',
                'taxes_impots',
                'autres',
            ]);

            $table->string('libelle', 200);
            $table->decimal('montant', 12, 2);
            $table->date('date_depense');
            $table->integer('mois');
            $table->integer('annee');
            $table->string('fournisseur', 150)->nullable();
            $table->string('numero_facture_ext', 100)->nullable();
            $table->string('justificatif_url', 500)->nullable();
            $table->enum('mode_paiement', ['cash', 'virement', 'cheque', 'cib'])->default('cash');
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('validee');
            $table->uuid('saisie_par')->nullable();
            $table->uuid('validee_par')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('budget_previsionnel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->integer('annee');
            $table->integer('mois')->nullable();
            $table->enum('categorie', [
                'salaires_enseignants',
                'salaires_personnel',
                'loyer',
                'electricite_gaz',
                'eau',
                'telephone_internet',
                'fournitures_bureau',
                'fournitures_pedagogiques',
                'maintenance_reparation',
                'assurance',
                'publicite_marketing',
                'transport',
                'cantine_restauration',
                'taxes_impots',
                'autres',
            ]);
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
