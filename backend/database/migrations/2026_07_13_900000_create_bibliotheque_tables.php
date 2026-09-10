<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livres_bibliotheque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('titre');
            $table->string('auteur')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('editeur')->nullable();
            $table->integer('annee_edition')->nullable();
            $table->string('categorie')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->integer('nb_exemplaires')->default(1);
            $table->integer('nb_disponibles')->default(1);
            $table->string('code_barre', 50)->nullable()->unique();
            $table->string('emplacement')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'titre']);
            $table->index(['tenant_id', 'isbn']);
            $table->index(['tenant_id', 'code_barre']);
        });

        Schema::create('emprunts_bibliotheque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('livre_id');
            $table->uuid('emprunteur_id');
            $table->string('type_emprunteur')->default('eleve');
            $table->string('nom_emprunteur');
            $table->date('date_emprunt');
            $table->date('date_retour_prevue');
            $table->date('date_retour_effective')->nullable();
            $table->string('statut')->default('en_cours');
            $table->decimal('amende', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('livre_id')->references('id')->on('livres_bibliotheque');
            $table->index(['tenant_id', 'statut']);
            $table->index(['tenant_id', 'emprunteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprunts_bibliotheque');
        Schema::dropIfExists('livres_bibliotheque');
    }
};
