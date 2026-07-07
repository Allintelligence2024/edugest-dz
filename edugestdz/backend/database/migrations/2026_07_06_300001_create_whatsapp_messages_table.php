<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('message_id')->nullable()->unique();
            $table->string('from_number', 20)->nullable();
            $table->string('to_number', 20)->nullable();
            $table->enum('direction', ['in', 'out'])->default('out');
            $table->enum('type', ['text', 'template', 'interactive'])->default('text');
            $table->text('content')->nullable();
            $table->string('template_name')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_wp_status');
            $table->index(['tenant_id', 'created_at'], 'idx_wp_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
