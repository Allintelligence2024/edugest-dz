<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('matricule', 20)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('nom_ar', 100)->nullable();
            $table->string('prenom_ar', 100)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance', 100)->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('adresse')->nullable();
            $table->unsignedSmallInteger('wilaya_id')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('diplome', 200)->nullable();
            $table->string('specialite', 200)->nullable();
            $table->integer('experience_annees')->default(0);
            $table->enum('type_contrat', ['CDI','CDD','vacataire','freelance','stagiaire'])->nullable();
            $table->date('date_embauche')->nullable();
            $table->decimal('salaire_base', 10, 2)->nullable();
            $table->decimal('taux_horaire', 8, 2)->nullable();
            $table->string('num_securite_sociale', 20)->nullable();
            $table->string('num_cnas', 20)->nullable();
            $table->string('rib_bancaire', 25)->nullable();
            $table->string('banque', 100)->nullable();
            $table->enum('statut', ['actif','congé','suspendu','démissionné'])->default('actif');
            $table->text('note_interne')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('matieres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100)->nullable();
            $table->string('couleur', 7)->default('#1E5EBC');
            $table->text('description')->nullable();
            $table->enum('statut', ['actif','inactif'])->default('actif');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('salles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('nom', 100);
            $table->integer('capacite')->default(0);
            $table->text('equipements')->nullable();
            $table->string('localisation', 200)->nullable();
            $table->enum('statut', ['disponible','occupée','maintenance'])->default('disponible');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('groupes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('matiere_id')->nullable();
            $table->string('nom', 100);
            $table->string('niveau_scolaire', 20)->nullable();
            $table->integer('capacite_max')->default(20);
            $table->enum('statut', ['actif','inactif','complet'])->default('actif');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('matiere_id')->references('id')->on('matieres')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupes');
        Schema::dropIfExists('salles');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('enseignants');
    }
};
