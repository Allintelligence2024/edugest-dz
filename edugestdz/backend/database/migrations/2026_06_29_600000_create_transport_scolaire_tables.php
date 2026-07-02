<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuits_transport', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('nom', 100);
            $table->string('description', 300)->nullable();
            $table->uuid('chauffeur_id')->nullable();
            $table->string('vehicule_immat', 30)->nullable();
            $table->string('vehicule_marque', 50)->nullable();
            $table->unsignedSmallInteger('capacite')->default(20);
            $table->decimal('tarif_mensuel', 10, 2)->default(0);
            $table->string('type_abonnement')->default('mensuel');
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            $table->date('date_controle_technique')->nullable();
            $table->date('date_expiration_assurance')->nullable();
            $table->date('date_vidange')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('chauffeur_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('set null');
        });

        Schema::create('arrets_bus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('circuit_id');

            $table->string('nom', 100);
            $table->string('adresse', 200)->nullable();
            $table->string('wilaya', 50)->nullable();
            $table->unsignedTinyInteger('ordre');
            $table->time('heure_matin')->nullable();
            $table->time('heure_soir')->nullable();
            $table->boolean('actif')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->unique(['circuit_id', 'ordre']);
        });

        Schema::create('transport_eleves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('circuit_id');
            $table->uuid('arret_id');

            $table->string('abonnement')->default('aller_retour');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->decimal('tarif_mensuel_applique', 10, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->foreign('arret_id')->references('id')->on('arrets_bus')->onDelete('cascade');
            $table->unique(['tenant_id', 'eleve_id', 'circuit_id', 'date_debut']);
        });

        Schema::create('pointage_bus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('circuit_id');
            $table->uuid('eleve_id');
            $table->uuid('arret_id');

            $table->date('date');
            $table->string('trajet')->default('matin');
            $table->string('statut')->default('monte');
            $table->time('heure_montee')->nullable();
            $table->boolean('sms_parent_envoye')->default(false);
            $table->timestamp('sms_envoye_at')->nullable();
            $table->string('signale_par', 30)->default('chauffeur');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id', 'circuit_id', 'eleve_id', 'date', 'trajet']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointage_bus');
        Schema::dropIfExists('transport_eleves');
        Schema::dropIfExists('arrets_bus');
        Schema::dropIfExists('circuits_transport');
    }
};
