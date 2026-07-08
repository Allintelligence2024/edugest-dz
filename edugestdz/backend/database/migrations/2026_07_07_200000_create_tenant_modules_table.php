<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('module_key');
            $table->boolean('actif')->default(true);
            $table->timestamp('desactive_le')->nullable();
            $table->uuid('modifie_par')->nullable();
            $table->text('raison')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_key'], 'uniq_tenant_module');
            $table->index(['tenant_id', 'actif'], 'idx_module_tenant_actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
