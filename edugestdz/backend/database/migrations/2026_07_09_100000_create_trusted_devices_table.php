<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 36);
            $table->string('device_hash', 64);
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_hash'], 'uk_user_device');
            $table->index('user_id', 'idx_td_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
