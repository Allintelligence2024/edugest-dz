<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('groupe_id');
            $table->string('titre', 200);
            $table->string('type_eval');
            $table->date('date_evaluation');
            $table->decimal('note_sur', 5, 2)->default(20);
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->string('trimestre');
            $table->text('description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('groupe_id')->references('id')->on('groupes')->onDelete('cascade');
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('evaluation_id');
            $table->uuid('eleve_id');
            $table->decimal('note', 5, 2)->nullable();
            $table->boolean('absent')->default(false);
            $table->string('appreciation', 50)->nullable();
            $table->string('commentaire', 200)->nullable();
            $table->uuid('saisie_par')->nullable();
            $table->timestamps();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['evaluation_id', 'eleve_id']);
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('seance_id');
            $table->uuid('eleve_id');
            $table->string('statut');
            $table->text('motif')->nullable();
            $table->time('heure_arrivee')->nullable();
            $table->uuid('saisi_par')->nullable();
            $table->timestamps();
            $table->foreign('seance_id')->references('id')->on('seances')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['seance_id', 'eleve_id']);
        });

        Schema::create('bulletins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('groupe_id');
            $table->string('trimestre');
            $table->string('annee_scolaire', 10);
            $table->decimal('moyenne_generale', 5, 2)->default(0);
            $table->integer('rang')->nullable();
            $table->integer('effectif_classe')->nullable();
            $table->text('appreciation_gen')->nullable();
            $table->string('fichier_url', 500)->nullable();
            $table->dateTime('genere_le')->nullable();
            $table->uuid('genere_par')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('groupe_id')->references('id')->on('groupes')->onDelete('cascade');
            $table->unique(['eleve_id', 'groupe_id', 'trimestre', 'annee_scolaire'], 'bulletin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('evaluations');
    }
};
