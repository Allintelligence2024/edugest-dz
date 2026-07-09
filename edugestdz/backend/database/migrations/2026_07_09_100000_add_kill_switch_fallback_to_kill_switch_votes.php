<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Table kill_switch_votes ────────────────────────────────────────
        // Créer si n'existe pas (auto-suffisant en CI qui repart d'une base vide)
        if (!Schema::hasTable('kill_switch_votes')) {
            Schema::create('kill_switch_votes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('initiator_id');
                $table->uuid('approver_id')->nullable();
                $table->string('action');
                $table->json('payload')->nullable();
                $table->string('status')->default('pending');
                // Colonnes fallback BDD pour persistance sans Redis
                $table->timestamp('active_since')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'expires_at'], 'idx_ksv_status');
                $table->index(['initiator_id', 'action'], 'idx_ksv_initiator');
            });
        } else {
            // Table existe déjà → ajouter seulement les colonnes manquantes
            Schema::table('kill_switch_votes', function (Blueprint $table) {
                if (!Schema::hasColumn('kill_switch_votes', 'active_since')) {
                    $table->timestamp('active_since')->nullable()->after('status');
                }
                if (!Schema::hasColumn('kill_switch_votes', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable()->after('active_since');
                }
            });
        }

        // ── Table kill_switch_state ────────────────────────────────────────
        // Table d'état global du KillSwitch (fallback BDD si Redis down)
        if (!Schema::hasTable('kill_switch_state')) {
            Schema::create('kill_switch_state', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->string('reason')->nullable();
                $table->string('activated_by')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });

            // État initial : KillSwitch inactif
            \DB::table('kill_switch_state')->insert([
                'is_active'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_switch_state');
        Schema::dropIfExists('kill_switch_votes');
    }
};
