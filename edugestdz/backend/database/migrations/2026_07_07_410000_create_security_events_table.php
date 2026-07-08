<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('severite')->default('warning');
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->string('path')->nullable();
            $table->json('details')->default('{}');
            $table->boolean('alerte_envoyee')->default(false);
            $table->timestamp('survenu_le')->useCurrent();
            $table->timestamps();

            $table->index(['type', 'survenu_le'],    'idx_sec_type_date');
            $table->index(['ip', 'survenu_le'],      'idx_sec_ip_date');
            $table->index(['user_id', 'survenu_le'], 'idx_sec_user_date');
            $table->index(['severite'],              'idx_sec_severite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
