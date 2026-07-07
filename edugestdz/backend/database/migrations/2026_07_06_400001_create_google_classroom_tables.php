<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_classroom_connexions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('email');
            $table->text('token');
            $table->timestamp('expires_at')->nullable();
            $table->string('google_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'uq_gc_connexion');
            $table->index(['tenant_id'], 'idx_gc_connexion_tenant');
        });

        Schema::create('google_course_liaisons', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('evaluation_id');
            $table->string('gc_course_id');
            $table->string('gc_coursework_id')->nullable();
            $table->string('gc_course_name')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->foreign('evaluation_id')->references('id')->on('evaluations')->onDelete('cascade');
            $table->unique(['evaluation_id'], 'uq_gc_liaison_eval');
            $table->index(['tenant_id', 'gc_course_id'], 'idx_gc_liaison_course');
        });

        Schema::create('google_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('liaison_id');
            $table->string('action');
            $table->string('status');
            $table->text('message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('liaison_id')->references('id')->on('google_course_liaisons')->onDelete('cascade');
            $table->index(['tenant_id', 'status'], 'idx_gc_log_status');
            $table->index(['liaison_id'], 'idx_gc_log_liaison');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sync_logs');
        Schema::dropIfExists('google_course_liaisons');
        Schema::dropIfExists('google_classroom_connexions');
    }
};
