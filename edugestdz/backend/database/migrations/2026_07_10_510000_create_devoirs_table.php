<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devoirs')) {
            Schema::create('devoirs', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('cours_id');
                $table->uuid('groupe_id')->nullable();
                $table->uuid('enseignant_user_id');
                $table->string('titre', 300);
                $table->text('description')->nullable();
                $table->date('date_remise');
                $table->integer('poids_notation')->default(0);
                $table->string('fichier_chemin', 500)->nullable();
                $table->boolean('eleves_notifies')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'groupe_id'], 'idx_devoirs_tenant_groupe');
                $table->index(['date_remise'], 'idx_devoirs_remise');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devoirs');
    }
};
