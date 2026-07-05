<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostics_eleves', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id')->unique();
            $table->string('niveau_global');
            $table->decimal('score_risque', 5, 2)->default(0);

            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->decimal('moyenne_trimestre_precedent', 5, 2)->nullable();
            $table->decimal('tendance', 5, 2)->nullable();

            $table->integer('nb_notes_sous_5')->default(0);
            $table->integer('nb_notes_sous_10')->default(0);
            $table->integer('nb_notes_consecutives_sous_5')->default(0);

            $table->jsonb('matieres_en_danger')->default('[]');
            $table->jsonb('matieres_excellentes')->default('[]');

            $table->integer('nb_absences_mois')->default(0);
            $table->integer('nb_retards_mois')->default(0);
            $table->integer('nb_billets_mois')->default(0);

            $table->boolean('rattrapage_requis')->default(false);
            $table->boolean('convocation_requise')->default(false);
            $table->boolean('sms_alerte_envoye')->default(false);
            $table->boolean('mention_excellence')->default(false);

            $table->timestamp('derniere_analyse')->nullable();
            $table->timestamp('prochaine_analyse')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'niveau_global'],  'idx_diag_tenant_niveau');
            $table->index(['tenant_id', 'score_risque'],   'idx_diag_tenant_score');
            $table->index(['rattrapage_requis'],            'idx_diag_rattrapage');
            $table->index(['convocation_requise'],          'idx_diag_convocation');
        });

        Schema::create('historique_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->string('niveau_global');
            $table->decimal('score_risque', 5, 2);
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->decimal('tendance', 5, 2)->nullable();
            $table->jsonb('details')->default('{}');
            $table->timestamp('analyse_le');

            $table->index(['eleve_id', 'analyse_le'], 'idx_histo_eleve_date');
            $table->index(['tenant_id', 'analyse_le'], 'idx_histo_tenant_date');
        });

        Schema::create('plans_rattrapage', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->uuid('enseignant_id')->nullable();
            $table->string('matiere');
            $table->text('objectifs');
            $table->text('programme');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['planifié', 'en_cours', 'terminé', 'annulé'])
                ->default('planifié');
            $table->text('resultat')->nullable();
            $table->uuid('cree_par')->nullable();
            $table->timestamps();

            $table->index(['eleve_id', 'statut'], 'idx_rattrapage_eleve_statut');
        });

        Schema::create('convocations_parents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->string('motif');
            $table->text('message');
            $table->enum('canal', ['sms', 'whatsapp', 'email', 'courrier'])->default('sms');
            $table->enum('statut', ['envoyée', 'confirmée', 'réalisée', 'annulée'])
                ->default('envoyée');
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamp('rendez_vous_le')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->uuid('cree_par')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'statut'],   'idx_conv_tenant_statut');
            $table->index(['eleve_id', 'statut'],    'idx_conv_eleve_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocations_parents');
        Schema::dropIfExists('plans_rattrapage');
        Schema::dropIfExists('historique_diagnostics');
        Schema::dropIfExists('diagnostics_eleves');
    }
};
