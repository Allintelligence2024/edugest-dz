<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eleves', 'qr_code')) {
            Schema::table('eleves', function ($table) {
                $table->string('qr_code', 500)->nullable()->after('photo_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eleves', 'qr_code')) {
            Schema::table('eleves', function ($table) {
                $table->dropColumn('qr_code');
            });
        }
    }
};
