<?php
// database/migrations/0001_create_tenants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom_etablissement', 200);
            $table->string('slug', 100)->unique();
            $table->string('type_etablissement')->default('centre_cours');
            $table->unsignedInteger('wilaya_id')->nullable();
            $table->unsignedInteger('commune_id')->nullable();
            $table->text('adresse')->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->unique()->nullable();
            $table->string('site_web', 200)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('nif', 20)->nullable();
            $table->string('nis', 20)->nullable();
            $table->string('registre_commerce', 50)->nullable();
            $table->string('plan_abonnement')->default('gratuit');
            $table->date('date_expiration')->nullable();
            $table->string('statut')->default('actif');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
