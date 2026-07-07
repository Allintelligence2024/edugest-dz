<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_cours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('enseignant_id');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('matiere')->nullable();
            $table->jsonb('niveaux_cibles')->default('[]');
            $table->string('langue')->default('ar');
            $table->string('duree_estimee')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('publie')->default(false);
            $table->boolean('certificat_actif')->default(true);
            $table->integer('seuil_completion')->default(80);
            $table->integer('nb_chapitres')->default(0);
            $table->integer('nb_lecons')->default(0);
            $table->integer('nb_inscrits')->default(0);
            $table->decimal('note_moyenne', 3, 1)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'publie'], 'idx_lms_cours_publie');
            $table->index(['tenant_id', 'enseignant_id'], 'idx_lms_cours_ens');
            $table->index(['tenant_id', 'matiere'], 'idx_lms_cours_matiere');
        });

        Schema::create('lms_chapitres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('cours_id');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(1);
            $table->boolean('publie')->default(true);
            $table->timestamps();

            $table->foreign('cours_id')->references('id')->on('lms_cours')->onDelete('cascade');
            $table->index(['cours_id', 'ordre'], 'idx_lms_chapitre_ordre');
        });

        Schema::create('lms_lecons', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('chapitre_id');
            $table->string('titre');
            $table->text('contenu')->nullable();
            $table->string('type')->default('texte');
            $table->string('ressource_url')->nullable();
            $table->string('ressource_nom')->nullable();
            $table->integer('duree_minutes')->nullable();
            $table->integer('ordre')->default(1);
            $table->boolean('gratuite')->default(false);
            $table->boolean('publiee')->default(true);
            $table->timestamps();

            $table->foreign('chapitre_id')->references('id')->on('lms_chapitres')->onDelete('cascade');
            $table->index(['chapitre_id', 'ordre'], 'idx_lms_lecon_ordre');
        });

        Schema::create('lms_quiz', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lecon_id');
            $table->string('titre');
            $table->integer('nb_questions')->default(0);
            $table->integer('duree_minutes')->default(30);
            $table->integer('seuil_reussite')->default(60);
            $table->integer('nb_tentatives_max')->default(3);
            $table->boolean('correction_immediate')->default(true);
            $table->boolean('ordre_aleatoire')->default(false);
            $table->timestamps();

            $table->foreign('lecon_id')->references('id')->on('lms_lecons')->onDelete('cascade');
        });

        Schema::create('lms_questions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('quiz_id');
            $table->string('type')->default('qcm');
            $table->text('enonce');
            $table->jsonb('options')->default('[]');
            $table->text('explication')->nullable();
            $table->integer('points')->default(1);
            $table->integer('ordre')->default(1);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('lms_quiz')->onDelete('cascade');
        });

        Schema::create('lms_inscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('cours_id');
            $table->uuid('eleve_id');
            $table->uuid('tenant_id');
            $table->enum('statut', ['actif', 'suspendu', 'termine'])->default('actif');
            $table->integer('progression_pct')->default(0);
            $table->integer('nb_lecons_completees')->default(0);
            $table->integer('temps_total_minutes')->default(0);
            $table->timestamp('derniere_activite')->nullable();
            $table->timestamp('complete_le')->nullable();
            $table->string('certificat_url')->nullable();
            $table->timestamps();

            $table->unique(['cours_id', 'eleve_id'], 'uniq_lms_insc');
            $table->foreign('cours_id')->references('id')->on('lms_cours')->onDelete('cascade');
            $table->index(['eleve_id', 'statut'], 'idx_lms_insc_eleve');
            $table->index(['tenant_id', 'cours_id'], 'idx_lms_insc_tenant');
        });

        Schema::create('lms_progression', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inscription_id');
            $table->uuid('lecon_id');
            $table->uuid('eleve_id');
            $table->boolean('completee')->default(false);
            $table->integer('temps_passe_secondes')->default(0);
            $table->integer('nb_vues')->default(0);
            $table->timestamp('completee_le')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'lecon_id'], 'uniq_lms_prog');
            $table->index(['eleve_id', 'completee'], 'idx_lms_prog_eleve');
        });

        Schema::create('lms_tentatives_quiz', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('quiz_id');
            $table->uuid('eleve_id');
            $table->uuid('inscription_id');
            $table->integer('score')->default(0);
            $table->integer('score_max')->default(0);
            $table->integer('pourcentage')->default(0);
            $table->boolean('reussi')->default(false);
            $table->integer('duree_secondes')->default(0);
            $table->jsonb('reponses')->default('{}');
            $table->integer('numero_tentative')->default(1);
            $table->timestamp('debut_le');
            $table->timestamp('fin_le')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('lms_quiz')->onDelete('cascade');
            $table->index(['quiz_id', 'eleve_id'], 'idx_lms_tentative_quiz');
            $table->index(['eleve_id', 'reussi'], 'idx_lms_tentative_eleve');
        });

        Schema::create('lms_devoirs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lecon_id');
            $table->uuid('eleve_id');
            $table->uuid('inscription_id');
            $table->string('fichier_url')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->text('commentaire_eleve')->nullable();
            $table->enum('statut', ['soumis', 'corrige', 'retourne'])->default('soumis');
            $table->decimal('note', 5, 2)->nullable();
            $table->decimal('note_max', 5, 2)->default(20);
            $table->text('feedback_enseignant')->nullable();
            $table->uuid('corrige_par')->nullable();
            $table->timestamp('corrige_le')->nullable();
            $table->timestamp('soumis_le');
            $table->timestamps();

            $table->foreign('lecon_id')->references('id')->on('lms_lecons')->onDelete('cascade');
            $table->index(['lecon_id', 'eleve_id'], 'idx_lms_devoir_lecon');
            $table->index(['eleve_id', 'statut'], 'idx_lms_devoir_eleve');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_devoirs');
        Schema::dropIfExists('lms_tentatives_quiz');
        Schema::dropIfExists('lms_progression');
        Schema::dropIfExists('lms_inscriptions');
        Schema::dropIfExists('lms_questions');
        Schema::dropIfExists('lms_quiz');
        Schema::dropIfExists('lms_lecons');
        Schema::dropIfExists('lms_chapitres');
        Schema::dropIfExists('lms_cours');
    }
};
