<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('groupe_id');
            $table->string('annee_scolaire', 10);
            $table->date('date_inscription');
            $table->decimal('frais_inscription', 10, 2)->default(0);
            $table->boolean('frais_paye')->default(false);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('statut')->default('en_attente');
            $table->text('motif_annulation')->nullable();
            $table->uuid('inscrit_par')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('groupe_id')->references('id')->on('groupes')->onDelete('cascade');
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('enseignant_id');
            $table->uuid('matiere_id')->nullable();
            $table->uuid('groupe_id');
            $table->uuid('salle_id')->nullable();
            $table->unsignedTinyInteger('jour_semaine')->comment('0=Dim,1=Lun,...,6=Sam');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('type_cours')->default('groupe');
            $table->string('recurrence')->default('hebdo');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('tarif_seance', 10, 2)->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->foreign('groupe_id')->references('id')->on('groupes')->onDelete('cascade');
            $table->foreign('salle_id')->references('id')->on('salles')->onDelete('set null');
        });

        Schema::create('seances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('cours_id');
            $table->date('date_seance');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->string('statut')->default('planifiée');
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
            $table->foreign('cours_id')->references('id')->on('cours')->onDelete('cascade');
            $table->unique(['cours_id', 'date_seance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
        Schema::dropIfExists('cours');
        Schema::dropIfExists('inscriptions');
    }
};
