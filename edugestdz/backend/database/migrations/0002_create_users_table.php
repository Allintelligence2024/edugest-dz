<?php
// database/migrations/0002_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 150)->unique();
            $table->string('telephone', 20)->nullable();
            $table->string('password');
            $table->string('avatar_url', 500)->nullable();
            $table->string('langue')->default('fr');
            $table->string('theme')->default('light');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->dateTime('derniere_connexion')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
