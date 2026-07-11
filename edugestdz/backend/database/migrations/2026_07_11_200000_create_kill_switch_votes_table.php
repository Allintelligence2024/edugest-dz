<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kill_switch_votes')) {
            return;
        }

        Schema::create('kill_switch_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('initiator_id', 36);
            $table->string('approver_id', 36)->nullable();
            $table->string('action', 50);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['initiator_id', 'status'], 'idx_ksv_initiator');
            $table->index(['status', 'expires_at'], 'idx_ksv_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_switch_votes');
    }
};
