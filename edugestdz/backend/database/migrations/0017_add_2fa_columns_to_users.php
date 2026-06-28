<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->string('two_factor_type', 10)->nullable()->after('two_factor_confirmed_at');
            $table->unsignedTinyInteger('login_attempts')->default(0)->after('two_factor_type');
            $table->timestamp('locked_until')->nullable()->after('login_attempts');
            $table->string('two_factor_phone', 20)->nullable()->after('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_type',
                'login_attempts',
                'locked_until',
                'two_factor_phone',
            ]);
        });
    }
};
