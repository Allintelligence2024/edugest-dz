<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('nom', 150);
            $table->string('reference', 50)->nullable();
            $table->string('qr_code', 100)->nullable()->unique();

            $table->string('categorie');

            $table->string('unite', 20)->default('pièce');
            $table->uuid('salle_id')->nullable();
            $table->string('localisation', 100)->nullable();

            $table->integer('quantite_stock')->default(0);
            $table->integer('quantite_minimum')->default(0);

            $table->string('etat')
                  ->default('bon');

            $table->decimal('valeur_unitaire', 10, 2)->nullable();
            $table->date('date_acquisition')->nullable();
            $table->string('fournisseur', 150)->nullable();
            $table->string('numero_serie', 100)->nullable();

            $table->boolean('est_immobilise')->default(false);
            $table->boolean('actif')->default(true);

            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            $table->string('type')
                  ->default('entree');
            $table->integer('quantite');
            $table->integer('quantite_avant')->default(0);
            $table->integer('quantite_apres')->default(0);
            $table->string('motif', 200)->nullable();
            $table->string('reference_doc', 100)->nullable();
            $table->uuid('saisie_par')->nullable();
            $table->date('date_mouvement');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('article_id')
                ->references('id')->on('articles_stock')
                ->onDelete('cascade');
        });

        Schema::create('prets_materiel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            $table->uuid('emprunteur_id')->nullable();
            $table->string('type_emprunteur')
                  ->default('enseignant');
            $table->string('nom_emprunteur', 150)->nullable();

            $table->integer('quantite')->default(1);
            $table->date('date_pret');
            $table->date('date_retour_prevue');
            $table->date('date_retour_effective')->nullable();
            $table->string('statut')
                  ->default('en_cours');
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('article_id')
                ->references('id')->on('articles_stock')
                ->onDelete('cascade');
        });

        Schema::create('bons_commande', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('numero', 30)->unique();
            $table->string('fournisseur', 150);
            $table->string('fournisseur_contact', 150)->nullable();
            $table->date('date_commande');
            $table->date('date_livraison_prevue')->nullable();
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->string('statut')
                  ->default('brouillon');
            $table->text('note')->nullable();
            $table->string('fichier_url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('lignes_bon_commande', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('bon_commande_id');
            $table->uuid('article_id')->nullable();

            $table->string('designation', 200);
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bon_commande_id')
                ->references('id')->on('bons_commande')
                ->onDelete('cascade');
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bon_commande');
        Schema::dropIfExists('bons_commande');
        Schema::dropIfExists('prets_materiel');
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('articles_stock');
    }
};
