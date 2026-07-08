<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 36);
            $table->string('challenge_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_dc_user');
            $table->index(['user_id', 'invalidated_at'], 'idx_dc_user_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_challenges');
    }
};
