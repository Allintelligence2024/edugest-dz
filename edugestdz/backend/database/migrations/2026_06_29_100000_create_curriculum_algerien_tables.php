<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();
        });

        Schema::create('niveaux_scolaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('palier_id');
            $table->string('code', 10)->unique();
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();
            $table->foreign('palier_id')->references('id')->on('paliers')->onDelete('cascade');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->jsonb('niveaux_applicables');
            $table->timestamps();
        });

        Schema::create('matieres_curriculum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('niveau_id');
            $table->uuid('branche_id')->nullable();
            $table->string('matiere_fr', 100);
            $table->string('matiere_ar', 100);
            $table->unsignedTinyInteger('coefficient');
            $table->unsignedTinyInteger('volume_horaire_hebdo')->nullable();
            $table->boolean('est_principale')->default(false);
            $table->boolean('est_facultatif')->default(false);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
            $table->foreign('niveau_id')->references('id')->on('niveaux_scolaires')->onDelete('cascade');
            $table->foreign('branche_id')->references('id')->on('branches')->onDelete('set null');
            $table->unique(['niveau_id', 'branche_id', 'matiere_fr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres_curriculum');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('niveaux_scolaires');
        Schema::dropIfExists('paliers');
    }
};
