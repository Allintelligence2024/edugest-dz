<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('causer_id')->nullable()->after('subject_id');
            if (!Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['causer_id']);
            if (Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
