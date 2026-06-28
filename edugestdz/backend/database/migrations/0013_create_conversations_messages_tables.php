<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('sujet', 200)->nullable();
                $table->jsonb('participants');
                $table->jsonb('lu_par')->default('[]');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('conversation_id');
                $table->uuid('expediteur_id');
                $table->text('message')->nullable();
                $table->string('type_message')->default('texte');
                $table->string('fichier_url', 500)->nullable();
                $table->string('fichier_nom')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
                $table->index('conversation_id');
                $table->index('expediteur_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
