<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profils_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->unique();
            $table->string('nom_etablissement');
            $table->text('description')->nullable();
            $table->string('adresse');
            $table->string('wilaya', 60);
            $table->string('commune', 60)->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->string('logo_url')->nullable();
            $table->jsonb('photos_urls')->default('[]');
            $table->jsonb('matieres_enseignees')->default('[]');
            $table->jsonb('niveaux_couverts')->default('[]');
            $table->jsonb('horaires')->default('{}');
            $table->decimal('tarif_heure_min', 8, 2)->nullable();
            $table->decimal('tarif_heure_max', 8, 2)->nullable();
            $table->boolean('accepte_essai_gratuit')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('verifie')->default(false);
            $table->integer('nb_eleves_actifs')->default(0);
            $table->integer('annees_experience')->default(0);
            $table->decimal('note_moyenne', 3, 2)->default(0);
            $table->integer('nb_avis')->default(0);
            $table->timestamps();

            $table->index(['wilaya', 'visible'], 'idx_profil_wilaya_visible');
            $table->index(['note_moyenne', 'visible'], 'idx_profil_note_visible');
        });

        Schema::create('offres_cours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('enseignant_id')->nullable();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('matiere');
            $table->jsonb('niveaux')->default('[]');
            $table->enum('type', ['groupe', 'individuel', 'en_ligne'])->default('individuel');
            $table->decimal('tarif_heure', 8, 2);
            $table->integer('duree_seance')->default(60);
            $table->integer('nb_places_max')->nullable();
            $table->boolean('essai_gratuit')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matiere', 'active'], 'idx_offre_matiere_active');
            $table->index(['tenant_id', 'active'], 'idx_offre_tenant_active');
        });

        Schema::create('reservations_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('offre_id');
            $table->uuid('parent_id');
            $table->uuid('eleve_id');
            $table->uuid('tenant_id');
            $table->dateTime('date_souhaitee');
            $table->integer('duree_minutes')->default(60);
            $table->enum('type', ['essai', 'cours_regulier', 'cours_unique'])->default('cours_unique');
            $table->enum('statut', [
                'en_attente', 'confirmee', 'annulee_parent',
                'annulee_centre', 'terminee', 'no_show',
            ])->default('en_attente');
            $table->decimal('montant', 10, 2)->default(0);
            $table->enum('statut_paiement', ['gratuit', 'en_attente', 'paye', 'rembourse'])->default('en_attente');
            $table->uuid('paiement_id')->nullable();
            $table->text('message_parent')->nullable();
            $table->text('reponse_centre')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('offre_id')->references('id')->on('offres_cours')->onDelete('cascade');
            $table->index(['parent_id', 'statut'], 'idx_resa_parent_statut');
            $table->index(['tenant_id', 'statut'], 'idx_resa_tenant_statut');
            $table->index(['date_souhaitee', 'statut'], 'idx_resa_date_statut');
        });

        Schema::create('avis_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('parent_id');
            $table->uuid('reservation_id')->nullable();
            $table->tinyInteger('note')->unsigned();
            $table->string('titre')->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('verifie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'parent_id', 'reservation_id'], 'uniq_avis_parent_resa');
            $table->index(['tenant_id', 'visible'], 'idx_avis_tenant_visible');
        });

        Schema::create('favoris_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('parent_id');
            $table->uuid('tenant_id');
            $table->timestamps();

            $table->unique(['parent_id', 'tenant_id'], 'uniq_favori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoris_marketplace');
        Schema::dropIfExists('avis_marketplace');
        Schema::dropIfExists('reservations_marketplace');
        Schema::dropIfExists('offres_cours');
        Schema::dropIfExists('profils_marketplace');
    }
};
