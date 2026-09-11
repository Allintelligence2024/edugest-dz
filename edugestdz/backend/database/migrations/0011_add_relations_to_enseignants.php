<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table: Enseignant <-> Matiere
        Schema::create('enseignant_matiere', function (Blueprint $table) {
            $table->uuid('enseignant_id');
            $table->uuid('matiere_id');
            $table->string('niveau_scolaire', 50)->nullable();
            $table->boolean('est_principal')->default(false);
            $table->timestamps();
            $table->primary(['enseignant_id', 'matiere_id']);
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->foreign('matiere_id')->references('id')->on('matieres')->onDelete('cascade');
        });

        // Add disponibilites JSON column to enseignants
        Schema::table('enseignants', function (Blueprint $table) {
            $table->json('disponibilites')->nullable()->after('note_interne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignant_matiere');
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn('disponibilites');
        });
    }
};
