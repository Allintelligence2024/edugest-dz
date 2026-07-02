<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('numero_facture', 30)->unique();
            $table->uuid('eleve_id');
            $table->unsignedTinyInteger('mois');
            $table->year('annee');
            $table->date('date_emission');
            $table->date('date_echeance');
            $table->decimal('sous_total', 12, 2)->default(0);
            $table->decimal('remise_pct', 5, 2)->default(0);
            $table->decimal('remise_montant', 12, 2)->default(0);
            $table->decimal('total_ttc', 12, 2)->default(0);
            $table->string('fichier_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->default('brouillon');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
        });

        Schema::create('lignes_facture', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facture_id');
            $table->string('description', 300);
            $table->decimal('quantite', 8, 2)->default(1);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('total', 12, 2);
            $table->string('type_ligne')->default('cours');
            $table->foreign('facture_id')->references('id')->on('factures')->onDelete('cascade');
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('facture_id');
            $table->uuid('eleve_id')->nullable();
            $table->decimal('montant', 12, 2);
            $table->string('mode_paiement');
            $table->date('date_paiement');
            $table->string('reference_trans', 100)->nullable();
            $table->string('recu_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recu_par')->nullable();
            $table->string('statut')->default('confirmé');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('facture_id')->references('id')->on('factures')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('lignes_facture');
        Schema::dropIfExists('factures');
    }
};
