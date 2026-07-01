<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locaux_batiment', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 100);
            $table->enum('type', [
                'salle_cours', 'bureau', 'couloir', 'cour',
                'sanitaires', 'cantine', 'gymnase', 'entree',
                'parking', 'laboratoire', 'bibliotheque', 'autre',
            ])->default('salle_cours');
            $table->string('etage', 20)->nullable();
            $table->float('superficie_m2')->nullable();
            $table->enum('etat_general', ['bon', 'moyen', 'mauvais', 'critique'])
                  ->default('bon');
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('prestataires_entretien', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 150);
            $table->enum('specialite', [
                'plomberie', 'electricite', 'peinture', 'climatisation',
                'menuiserie', 'maconnerie', 'nettoyage', 'informatique',
                'jardinage', 'securite', 'general', 'autre',
            ])->default('general');
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 200)->nullable();
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('interventions_entretien', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('local_id')->nullable();
            $table->uuid('prestataire_id')->nullable();

            $table->string('titre', 200);
            $table->text('description')->nullable();

            $table->enum('type', [
                'panne',
                'degradation',
                'entretien_preventif',
                'renovation',
                'nettoyage',
                'inspection',
            ])->default('panne');

            $table->enum('priorite', ['urgente', 'haute', 'normale', 'basse'])
                  ->default('normale');

            $table->enum('statut', [
                'signale',
                'en_cours',
                'en_attente',
                'resolu',
                'annule',
            ])->default('signale');

            $table->date('date_signalement');
            $table->date('date_debut_intervention')->nullable();
            $table->date('date_resolution')->nullable();
            $table->date('date_entretien_suivant')->nullable();

            $table->decimal('cout_estime', 10, 2)->nullable();
            $table->decimal('cout_reel', 10, 2)->nullable();
            $table->uuid('depense_id')->nullable();

            $table->string('photos_avant', 1000)->nullable();
            $table->string('photos_apres', 1000)->nullable();

            $table->uuid('signale_par')->nullable();
            $table->uuid('assigne_a')->nullable();
            $table->text('rapport_intervention')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('local_id')->references('id')->on('locaux_batiment')->onDelete('set null');
            $table->foreign('prestataire_id')->references('id')->on('prestataires_entretien')->onDelete('set null');
        });

        Schema::create('entretiens_preventifs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('local_id')->nullable();
            $table->uuid('prestataire_id')->nullable();

            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->enum('frequence', [
                'hebdomadaire', 'mensuel', 'trimestriel',
                'semestriel', 'annuel', 'biennal',
            ])->default('annuel');

            $table->date('prochaine_echeance');
            $table->date('derniere_realisation')->nullable();
            $table->decimal('cout_estime', 10, 2)->nullable();
            $table->boolean('actif')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entretiens_preventifs');
        Schema::dropIfExists('interventions_entretien');
        Schema::dropIfExists('prestataires_entretien');
        Schema::dropIfExists('locaux_batiment');
    }
};
