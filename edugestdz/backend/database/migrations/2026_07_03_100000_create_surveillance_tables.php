<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras_config', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('nom');
            $table->string('serial_no')->unique();
            $table->string('ip_locale')->nullable();
            $table->integer('canal')->default(1);
            $table->string('type')->default('entree');
            $table->string('localisation')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->time('heure_ouverture')->default('07:00');
            $table->time('heure_fermeture')->default('20:00');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'actif'], 'idx_cameras_tenant_actif');
            $table->index(['serial_no'], 'idx_cameras_serial');
        });

        Schema::create('alertes_surveillance', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('camera_id')->nullable();
            $table->string('serial_no');
            $table->string('type_alerte');
            $table->string('niveau')->default('warning');
            $table->string('canal')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamp('survenu_le');
            $table->boolean('traite')->default(false);
            $table->uuid('traite_par')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->string('note_admin')->nullable();
            $table->boolean('sms_envoye')->default(false);
            $table->boolean('push_envoye')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'traite'], 'idx_alertes_tenant_traite');
            $table->index(['tenant_id', 'niveau'], 'idx_alertes_tenant_niveau');
            $table->index(['serial_no', 'survenu_le'], 'idx_alertes_serial_date');
            $table->index(['tenant_id', 'survenu_le'], 'idx_alertes_tenant_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_surveillance');
        Schema::dropIfExists('cameras_config');
    }
};
