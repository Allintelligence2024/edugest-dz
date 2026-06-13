<?php
// database/migrations/0004_create_referentiels_algeriens.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Wilayas d'Algérie (48) ──
        Schema::create('wilayas', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('code', 5);
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
        });

        // ── Communes ──
        Schema::create('communes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedSmallInteger('wilaya_id');
            $table->string('code_postal', 10)->nullable();
            $table->string('nom_fr', 150);
            $table->string('nom_ar', 150)->nullable();
            $table->foreign('wilaya_id')->references('id')->on('wilayas');
        });

        // ── Calendrier Scolaire Algérien ──
        Schema::create('calendrier_scolaire', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('annee_scolaire', 10);
            $table->string('evenement', 200);
            $table->enum('type', ['vacances', 'ferie', 'examen', 'rentree']);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->unsignedSmallInteger('wilaya_id')->nullable();
            $table->timestamps();
        });

        // ── Audit Logs ──
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('action', 100);
            $table->string('table_concernee', 50)->nullable();
            $table->uuid('enregistrement_id')->nullable();
            $table->jsonb('anciennes_valeurs')->nullable();
            $table->jsonb('nouvelles_valeurs')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('calendrier_scolaire');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('wilayas');
    }
};
