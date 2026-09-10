<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->string('role_id', 36)->nullable();
            $table->string('resource', 100);
            $table->string('field', 100);
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'resource'], 'idx_fp_tenant_resource');
            $table->index(['tenant_id', 'role_id', 'resource'], 'idx_fp_tenant_role_resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_permissions');
    }
};
