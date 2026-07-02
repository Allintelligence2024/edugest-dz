<?php
// database/migrations/0005_create_eleves_parents_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Élèves ──
        Schema::create('eleves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('numero_inscription', 20)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('nom_ar', 100)->nullable();
            $table->string('prenom_ar', 100)->nullable();
            $table->date('date_naissance');
            $table->string('lieu_naissance', 100)->nullable();
            $table->string('sexe');
            $table->string('nationalite', 50)->default('Algérienne');
            $table->unsignedSmallInteger('wilaya_id')->nullable();
            $table->unsignedInteger('commune_id')->nullable();
            $table->text('adresse')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('ecole_origine', 200)->nullable();
            $table->string('niveau_scolaire');
            $table->string('statut')->default('actif');
            $table->text('notes_internes')->nullable();
            $table->string('qr_code', 500)->nullable();
            $table->decimal('budget_mensuel', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('wilaya_id')->references('id')->on('wilayas');
            $table->foreign('commune_id')->references('id')->on('communes');

            $table->index(['tenant_id', 'statut']);
            $table->index(['tenant_id', 'niveau_scolaire']);
            $table->index(['tenant_id', 'nom', 'prenom']);
        });

        // ── Parents ──
        Schema::create('parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('lien');
            $table->string('telephone_1', 20);
            $table->string('telephone_2', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('profession', 100)->nullable();
            $table->string('lieu_travail', 200)->nullable();
            $table->boolean('est_urgence')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Pivot Élève-Parent ──
        Schema::create('eleve_parent', function (Blueprint $table) {
            $table->uuid('eleve_id');
            $table->uuid('parent_id');
            $table->boolean('est_principal')->default(false);
            $table->primary(['eleve_id', 'parent_id']);
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleve_parent');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('eleves');
    }
};
