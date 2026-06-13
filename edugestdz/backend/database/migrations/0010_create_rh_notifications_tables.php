<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('enseignant_id');
            $table->enum('type_contrat', ['CDI','CDD','vacataire']);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('salaire', 10, 2);
            $table->decimal('taux_irg', 5, 2)->default(0);
            $table->decimal('taux_cnas', 5, 2)->default(9.00);
            $table->decimal('taux_casnos', 5, 2)->default(0);
            $table->string('fichier_url', 500)->nullable();
            $table->enum('statut', ['actif','expiré','résilié'])->default('actif');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
        });

        Schema::create('paies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('enseignant_id');
            $table->unsignedTinyInteger('mois');
            $table->year('annee');
            $table->decimal('salaire_base', 10, 2);
            $table->decimal('heures_travaillees', 6, 2)->nullable();
            $table->decimal('taux_horaire', 8, 2)->nullable();
            $table->decimal('primes', 10, 2)->default(0);
            $table->decimal('retenues_absences', 10, 2)->default(0);
            $table->decimal('irg', 10, 2)->default(0);
            $table->decimal('cnas', 10, 2)->default(0);
            $table->decimal('casnos', 10, 2)->default(0);
            $table->decimal('salaire_net', 10, 2);
            $table->enum('statut', ['calculé','validé','payé','annulé'])->default('calculé');
            $table->date('date_paiement')->nullable();
            $table->string('bulletin_url', 500)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->unique(['enseignant_id', 'mois', 'annee']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index();
            $table->string('type', 50)->default('info');
            $table->string('titre', 200);
            $table->text('message')->nullable();
            $table->string('lien', 500)->nullable();
            $table->boolean('est_lu')->default(false);
            $table->uuid('envoye_par')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('paies');
        Schema::dropIfExists('contrats');
    }
};
