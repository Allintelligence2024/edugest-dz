<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paies_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->decimal('salaire_base', 10, 2)->default(0);
            $table->unsignedSmallInteger('jours_travailles')->default(0);
            $table->unsignedSmallInteger('jours_ouvrables')->default(26);
            $table->decimal('retenues_absences', 10, 2)->default(0);
            $table->decimal('cnas', 10, 2)->default(0);
            $table->decimal('irg', 10, 2)->default(0);
            $table->decimal('salaire_net', 10, 2)->default(0);
            $table->string('statut')->default('brouillon');
            $table->date('date_paiement')->nullable();
            $table->string('fichier_url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
            $table->unique(['tenant_id', 'agent_id', 'mois', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paies_personnel');
    }
};
