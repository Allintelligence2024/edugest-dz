<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('absences_journalieres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->date('date_absence');
            $table->enum('statut', ['present','absent','retard','demi_journee'])->default('absent');
            $table->time('heure_arrivee')->nullable();
            $table->enum('signale_par', ['admin','badge','parent','auto'])->default('auto');
            $table->boolean('sms_parent_envoye')->default(false);
            $table->timestamp('sms_envoye_at')->nullable();
            $table->string('motif')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id','eleve_id','date_absence']);
        });

        Schema::create('justificatifs_absence', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('absence_id');
            $table->text('motif');
            $table->string('document_url', 500)->nullable();
            $table->enum('statut', ['en_attente','valide','refuse'])->default('en_attente');
            $table->uuid('valide_par')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();

            $table->foreign('absence_id')->references('id')->on('absences_journalieres')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatifs_absence');
        Schema::dropIfExists('absences_journalieres');
    }
};
