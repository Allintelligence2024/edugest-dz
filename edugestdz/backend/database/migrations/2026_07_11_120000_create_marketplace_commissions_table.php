<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('enseignant_id')->nullable();
            $table->uuid('reservation_id');
            $table->decimal('montant_total', 10, 2);
            $table->decimal('taux_commission', 5, 4);
            $table->decimal('montant_commission', 10, 2);
            $table->decimal('montant_enseignant', 10, 2);
            $table->string('statut')->default('en_attente');
            $table->string('plan_tenant')->nullable();
            $table->timestamp('paye_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('set null');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');

            $table->index(['statut', 'paye_le']);
            $table->index(['tenant_id', 'statut']);
            $table->index(['enseignant_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_commissions');
    }
};
