<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siem_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom', 100)->unique();
            $table->string('categorie', 50);
            $table->text('description')->nullable();
            $table->json('conditions');
            $table->unsignedTinyInteger('severite')->default(5);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('categorie', 'idx_sr_cat');
            $table->index('active', 'idx_sr_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siem_rules');
    }
};
