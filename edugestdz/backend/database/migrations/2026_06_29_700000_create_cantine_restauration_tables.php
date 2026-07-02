<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus_cantine', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->date('date_repas');
            $table->string('type_repas')->default('dejeuner');
            $table->string('plat_principal', 200);
            $table->string('accompagnement', 200)->nullable();
            $table->string('dessert', 150)->nullable();
            $table->string('boisson', 100)->nullable();
            $table->decimal('prix_unitaire', 8, 2)->default(0);
            $table->unsignedSmallInteger('nb_couverts_prevus')->default(0);
            $table->text('allergenes')->nullable();
            $table->text('note')->nullable();
            $table->boolean('publie')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'date_repas', 'type_repas']);
        });

        Schema::create('inscriptions_cantine', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');

            $table->string('type_abonnement')->default('mensuel');
            $table->string('regime')->default('normal');
            $table->string('allergies', 300)->nullable();
            $table->boolean('actif')->default(true);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('tarif_mensuel', 8, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id', 'eleve_id', 'date_debut']);
        });

        Schema::create('repas_journaliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('menu_id')->nullable();

            $table->date('date_repas');
            $table->string('type_repas')->default('dejeuner');
            $table->boolean('present')->default(false);
            $table->boolean('facture')->default(false);
            $table->decimal('prix_applique', 8, 2)->default(0);
            $table->string('signale_par', 30)->default('admin');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menus_cantine')->onDelete('set null');
            $table->unique(['tenant_id', 'eleve_id', 'date_repas', 'type_repas']);
        });

        Schema::create('stock_cuisine', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('article', 150);
            $table->string('categorie')->default('autres');
            $table->string('unite', 20)->default('kg');
            $table->decimal('quantite_stock', 10, 3)->default(0);
            $table->decimal('seuil_alerte', 10, 3)->default(0);
            $table->decimal('prix_unitaire', 8, 2)->nullable();
            $table->string('fournisseur', 150)->nullable();
            $table->date('date_peremption')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('mouvements_stock_cuisine', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('article_id');

            $table->string('type')->default('entree');
            $table->decimal('quantite', 10, 3);
            $table->string('motif', 200)->nullable();
            $table->uuid('saisie_par')->nullable();
            $table->date('date_mouvement');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('article_id')->references('id')->on('stock_cuisine')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock_cuisine');
        Schema::dropIfExists('stock_cuisine');
        Schema::dropIfExists('repas_journaliers');
        Schema::dropIfExists('inscriptions_cantine');
        Schema::dropIfExists('menus_cantine');
    }
};
