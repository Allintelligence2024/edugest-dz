<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pointage_enseignants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('enseignant_id');
            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->enum('methode', ['badge','qr','manuel'])->default('manuel');
            $table->string('badge_uid', 100)->nullable();
            $table->enum('statut', ['present','absent','retard','conge','maladie'])->default('present');
            $table->boolean('notif_eleves_envoye')->default(false);
            $table->boolean('impact_paie')->default(false);
            $table->decimal('retenue_dzd', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->unique(['tenant_id','enseignant_id','date']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('badge_uid', 100)->unique();
            $table->uuid('proprietaire_id');
            $table->enum('type_proprietaire', ['eleve','enseignant','personnel']);
            $table->boolean('actif')->default(true);
            $table->date('date_emission')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::dropIfExists('pointage_enseignants');
    }
};
