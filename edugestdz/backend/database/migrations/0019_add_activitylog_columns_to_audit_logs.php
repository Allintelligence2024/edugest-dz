<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 100)->nullable()->change();
            $table->string('log_name')->nullable()->after('id');
            $table->text('description')->nullable()->after('log_name');
            $table->string('subject_type')->nullable()->after('description');
            $table->string('subject_id')->nullable()->after('subject_type');
            $table->string('causer_type')->nullable()->after('user_id');
            $table->json('properties')->nullable()->after('nouvelles_valeurs');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['log_name', 'description', 'subject_type', 'subject_id', 'causer_type', 'properties']);
        });
    }
};
