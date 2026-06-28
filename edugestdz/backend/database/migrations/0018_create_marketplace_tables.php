<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Offres publiques ──
        Schema::create('offres_publiques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('enseignant_id')->nullable();
            $table->enum('type_offre', ['enseignant', 'centre']);
            $table->uuid('matiere_id');
            $table->string('niveau');
            $table->decimal('tarif_seance', 10, 2);
            $table->decimal('tarif_mensuel', 10, 2)->nullable();
            $table->enum('type_cours', ['presentiel', 'en_ligne', 'les_deux']);
            $table->integer('wilaya_id')->nullable()->unsigned();
            $table->text('adresse')->nullable();
            $table->integer('capacite_max')->default(1);
            $table->integer('places_restantes')->default(1);
            $table->text('description')->nullable();
            $table->enum('statut', ['active', 'inactive', 'archivee'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('set null');
            $table->foreign('matiere_id')->references('id')->on('matieres')->onDelete('cascade');
            $table->foreign('wilaya_id')->references('id')->on('wilayas')->onDelete('set null');
        });

        // ── Réservations ──
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('offre_id');
            $table->uuid('eleve_id');
            $table->enum('statut', ['en_attente', 'confirmee', 'payee', 'annulee', 'terminee'])->default('en_attente');
            $table->decimal('montant', 10, 2);
            $table->decimal('commission', 10, 2)->default(0);
            $table->string('mode_paiement')->nullable();
            $table->uuid('paiement_en_ligne_id')->nullable();
            $table->text('message')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('lien_visio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('offre_id')->references('id')->on('offres_publiques')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
        });

        // ── Avis ──
        Schema::create('avis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('reservation_id');
            $table->uuid('eleve_id');
            $table->uuid('enseignant_id');
            $table->integer('note');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('offres_publiques');
    }
};
