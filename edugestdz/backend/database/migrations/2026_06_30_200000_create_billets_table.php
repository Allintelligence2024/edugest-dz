<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');

            $table->string('type');

            $table->date('date_billet');
            $table->time('heure')->nullable();
            $table->string('motif', 300)->nullable();
            $table->boolean('parent_prevenu')->default(false);
            $table->uuid('etabli_par')->nullable();
            $table->string('fichier_url', 500)->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billets');
    }
};
