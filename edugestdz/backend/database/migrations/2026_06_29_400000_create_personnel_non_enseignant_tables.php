<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_non_enseignant', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            // Identité
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 300)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->date('date_naissance')->nullable();

            // Poste
            $table->string('poste');
            $table->string('poste_libelle', 100)->nullable();

            // Contrat
            $table->string('type_contrat')->default('CDI');
            $table->date('date_embauche');
            $table->date('date_fin_contrat')->nullable();
            $table->decimal('salaire_base', 10, 2)->default(0);
            $table->string('frequence_paie')->default('mensuel');

            // Statut
            $table->string('statut')->default('actif');
            $table->string('matricule', 30)->nullable()->unique();

            // Documents
            $table->string('num_ss', 30)->nullable();
            $table->string('num_cnas', 30)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');
        });

        // ── Pointage personnel non-enseignant ──
        Schema::create('pointage_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->string('methode')->default('manuel');
            $table->string('badge_uid', 100)->nullable();
            $table->string('statut')->default('present');
            $table->boolean('impact_paie')->default(false);
            $table->decimal('retenue_dzd', 10, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
            $table->unique(['tenant_id', 'agent_id', 'date']);
        });

        // ── Congés et absences planifiées ──
        Schema::create('conges_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('nb_jours')->default(1);
            $table->string('type')
                  ->default('conge_annuel');
            $table->text('motif')->nullable();
            $table->string('document_url', 500)->nullable();
            $table->string('statut')->default('en_attente');
            $table->uuid('approuve_par')->nullable();
            $table->timestamp('approuve_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges_personnel');
        Schema::dropIfExists('pointage_personnel');
        Schema::dropIfExists('personnel_non_enseignant');
    }
};
