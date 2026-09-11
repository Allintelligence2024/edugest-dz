<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jwt_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 64)->unique();
            $table->string('user_id', 36);
            $table->string('raison')->nullable();
            $table->timestamp('expire_le');
            $table->timestamp('blackliste_le')->useCurrent();

            $table->index(['jti'],      'idx_blacklist_jti');
            $table->index(['expire_le'],'idx_blacklist_expire');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jwt_blacklist');
    }
};
