<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('predictions_echec')) {
            Schema::create('predictions_echec', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');

                $table->decimal('probabilite_echec', 5, 2);
                $table->decimal('confiance', 5, 2);
                $table->string('horizon', 20)->default('4_semaines');

                $table->string('niveau_risque', 20);

                $table->json('features');
                $table->json('facteurs_risque');
                $table->json('recommandations');

                $table->string('moteur_version', 20)->default('logistique_v1');

                $table->boolean('confirme_par_directeur')->default(false);
                $table->text('note_directeur')->nullable();
                $table->timestamp('notifie_le')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'niveau_risque'], 'idx_pred_tenant_niveau');
                $table->index(['eleve_id', 'created_at'], 'idx_pred_eleve_date');
                $table->index(['probabilite_echec'], 'idx_pred_proba');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions_echec');
    }
};
