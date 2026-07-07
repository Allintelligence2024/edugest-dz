<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('google_classroom_connexions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('google_course_liaisons', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('google_sync_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('google_classroom_connexions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('google_course_liaisons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('google_sync_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
