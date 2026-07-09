<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter une colonne de fallback BDD au cas où Redis est down
        Schema::table('kill_switch_votes', function (Blueprint $table) {
            if (!Schema::hasColumn('kill_switch_votes', 'active_since')) {
                $table->timestamp('active_since')->nullable()->after('status');
                // Si non null → le KillSwitch est actif même sans Redis
            }
            if (!Schema::hasColumn('kill_switch_votes', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('active_since');
            }
        });

        // Table d'état global du KillSwitch (séparée des votes)
        if (!Schema::hasTable('kill_switch_state')) {
            Schema::create('kill_switch_state', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->string('reason')->nullable();
                $table->string('activated_by')->nullable();  // super_admin email
                $table->string('approved_by')->nullable();   // 2ème super_admin
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });

            // Insérer l'état initial (inactif)
            \DB::table('kill_switch_state')->insert([
                'is_active'    => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_switch_state');
        Schema::table('kill_switch_votes', function (Blueprint $table) {
            $table->dropColumnIfExists('active_since');
            $table->dropColumnIfExists('deactivated_at');
        });
    }
};
