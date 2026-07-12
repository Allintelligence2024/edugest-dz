<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_fractionnement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('facture_id');
            $table->uuid('eleve_id');
            $table->integer('nb_tranches')->default(2);
            $table->decimal('montant_total', 12, 2);
            $table->string('statut', 30)->default('actif'); // actif, terminé, annulé
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('facture_id')->references('id')->on('factures');
            $table->foreign('eleve_id')->references('id')->on('eleves');
        });

        Schema::create('tranches_fractionnement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('plan_id');
            $table->integer('numero'); // 1, 2, 3...
            $table->decimal('montant', 12, 2);
            $table->date('date_echeance');
            $table->string('statut', 30)->default('en_attente'); // en_attente, payée, en_retard, annulée
            $table->decimal('montant_paye', 12, 2)->default(0);
            $table->date('date_paiement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('plan_id')->references('id')->on('plans_fractionnement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tranches_fractionnement');
        Schema::dropIfExists('plans_fractionnement');
    }
};
