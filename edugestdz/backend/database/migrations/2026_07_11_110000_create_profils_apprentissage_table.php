<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('profils_apprentissage')) {
            Schema::create('profils_apprentissage', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id')->unique();

                $table->string('profil', 40);

                $table->decimal('stabilite_score', 5, 2);
                $table->decimal('tendance_long_terme', 6, 3);
                $table->decimal('variance_notes', 8, 4);
                $table->integer('nb_chutes_recuperees');
                $table->integer('nb_chutes_non_recuperees');
                $table->decimal('correlation_absences_notes', 5, 3)->nullable();

                $table->json('points_forts');
                $table->json('points_faibles');
                $table->json('historique_profils');

                $table->timestamp('calcule_le');
                $table->timestamps();

                $table->index(['tenant_id', 'profil'], 'idx_profil_tenant');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profils_apprentissage');
    }
};
