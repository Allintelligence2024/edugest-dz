<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('feedbacks_pedagogiques')) {
            Schema::create('feedbacks_pedagogiques', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');
                $table->uuid('enseignant_user_id');
                $table->uuid('cours_id')->nullable();
                $table->integer('trimestre');
                $table->tinyInteger('note_qualite')->default(3);
                $table->string('type_feedback', 50)->default('pedagogie');
                $table->text('commentaire')->nullable();
                $table->string('statut', 20)->default('soumis');
                $table->timestamps();

                $table->unique(
                    ['eleve_id', 'enseignant_user_id', 'trimestre'],
                    'uq_feedback_eleve_ens_trim'
                );
                $table->index(['tenant_id', 'statut'], 'idx_feedback_tenant_statut');
                $table->index(['enseignant_user_id'], 'idx_feedback_ens');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks_pedagogiques');
    }
};
