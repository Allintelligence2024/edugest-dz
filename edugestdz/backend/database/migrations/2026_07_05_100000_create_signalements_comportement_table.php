<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements_comportement', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->uuid('signale_par');
            $table->string('role_auteur');
            $table->string('type');
            $table->string('gravite')->default('normale');
            $table->text('description');
            $table->string('lieu')->nullable();
            $table->date('date_incident');
            $table->time('heure_incident')->nullable();
            $table->boolean('notifie_parent')->default(false);
            $table->boolean('vu_par_parent')->default(false);
            $table->timestamp('vu_le')->nullable();
            $table->boolean('traite')->default(false);
            $table->text('suite_donnee')->nullable();
            $table->uuid('traite_par')->nullable();
            $table->timestamps();

            $table->index(['eleve_id', 'date_incident'],  'idx_signal_eleve_date');
            $table->index(['tenant_id', 'traite'],        'idx_signal_tenant_traite');
            $table->index(['tenant_id', 'gravite'],       'idx_signal_tenant_gravite');
            $table->index(['signale_par', 'date_incident'],'idx_signal_auteur_date');
        });

        Schema::create('notifications_parent', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('parent_id');
            $table->uuid('eleve_id')->nullable();
            $table->string('type');
            $table->string('titre');
            $table->text('corps');
            $table->jsonb('meta')->default('{}');
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->boolean('push_envoye')->default(false);
            $table->boolean('sms_envoye')->default(false);
            $table->timestamps();

            $table->index(['parent_id', 'lu'],         'idx_notif_parent_lu');
            $table->index(['parent_id', 'created_at'], 'idx_notif_parent_date');
            $table->index(['eleve_id', 'type'],        'idx_notif_eleve_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_parent');
        Schema::dropIfExists('signalements_comportement');
    }
};
