<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('type')->default('BAC');
            $table->string('filiere')->nullable();
            $table->string('annee_scolaire', 10);
            $table->string('session')->default('principale');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('wilaya', 60)->nullable();
            $table->string('commune', 60)->nullable();
            $table->string('nom_centre')->nullable();
            $table->string('adresse_centre')->nullable();
            $table->integer('capacite_max')->default(0);
            $table->integer('max_candidats_par_salle')->default(20);
            $table->integer('max_candidats_libres_par_salle')->default(15);
            $table->integer('nb_surveillants_par_salle')->default(3);
            $table->enum('statut', ['brouillon','planifie','en_cours','termine','annule'])
                ->default('brouillon');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'annee_scolaire'], 'idx_session_tenant_type');
            $table->index(['tenant_id', 'statut'],                  'idx_session_statut');
        });

        Schema::create('epreuves_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->string('matiere');
            $table->string('code_matiere')->nullable();
            $table->decimal('coefficient', 4, 1)->default(1);
            $table->date('date_epreuve');
            $table->enum('moment', ['matin', 'apres_midi'])->default('matin');
            $table->time('heure_debut')->default('08:30');
            $table->time('heure_fin');
            $table->integer('duree_minutes')->default(120);
            $table->string('type_epreuve')->default('ecrit');
            $table->boolean('calculatrice_autorisee')->default(false);
            $table->boolean('documents_autorises')->default(false);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id', 'date_epreuve'], 'idx_epreuve_session_date');
        });

        Schema::create('salles_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->string('nom');
            $table->string('numero')->nullable();
            $table->string('batiment')->nullable();
            $table->string('etage')->nullable();
            $table->integer('capacite_totale')->default(20);
            $table->integer('nb_candidats_affectes')->default(0);
            $table->integer('nb_rangees')->nullable();
            $table->integer('nb_colonnes')->nullable();
            $table->boolean('climatisee')->default(false);
            $table->boolean('accessible_pmr')->default(false);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id'], 'idx_salle_session');
        });

        Schema::create('candidats_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->uuid('eleve_id')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('numero_inscription')->nullable()->unique();
            $table->string('type_candidat')->default('scolarise');
            $table->string('filiere')->nullable();
            $table->uuid('salle_id')->nullable();
            $table->integer('numero_place')->nullable();
            $table->string('rangee')->nullable();
            $table->integer('colonne')->nullable();
            $table->boolean('convocation_imprimee')->default(false);
            $table->boolean('present')->nullable();
            $table->timestamp('present_marque_le')->nullable();
            $table->boolean('besoins_speciaux')->default(false);
            $table->text('notes_speciaux')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->foreign('salle_id')->references('id')->on('salles_examen')->onDelete('set null');
            $table->index(['session_id', 'salle_id'],    'idx_candidat_session_salle');
            $table->index(['session_id', 'eleve_id'],    'idx_candidat_eleve');
            $table->index(['numero_inscription'],         'idx_candidat_num');
        });

        Schema::create('surveillants_examen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('session_id');
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('nom');
            $table->string('prenom');
            $table->string('specialite')->nullable();
            $table->string('commune_origine')->nullable();
            $table->string('role')->default('surveillant');
            $table->uuid('salle_id')->nullable();
            $table->string('salle_nom')->nullable();
            $table->boolean('disponible')->default(true);
            $table->boolean('convocation_imprimee')->default(false);
            $table->text('motif_exemption')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions_examen')->onDelete('cascade');
            $table->index(['session_id', 'salle_id'],    'idx_surv_session_salle');
            $table->index(['session_id', 'user_id'],     'idx_surv_user');
            $table->index(['tenant_id', 'disponible'],   'idx_surv_dispo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveillants_examen');
        Schema::dropIfExists('candidats_examen');
        Schema::dropIfExists('salles_examen');
        Schema::dropIfExists('epreuves_examen');
        Schema::dropIfExists('sessions_examen');
    }
};
