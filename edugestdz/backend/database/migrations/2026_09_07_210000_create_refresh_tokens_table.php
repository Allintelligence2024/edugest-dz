<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 — Refresh tokens persistés.
 *
 * Le frontend lisait `localStorage.getItem('refresh_token')` et appelait
 * /auth/refresh avec... un jeton que le backend n'a JAMAIS émis. Le flux de
 * refresh était donc mort : à l'expiration du JWT, l'utilisateur était
 * déconnecté sans préavis.
 *
 * On introduit un vrai refresh token, à durée de vie longue, stocké
 * uniquement sous forme de hash (une fuite de la base ne permet pas de
 * rejouer une session) et transporté par cookie httpOnly.
 *
 * Rotation : chaque usage consomme le jeton et en émet un nouveau. Si un
 * jeton déjà consommé est présenté, c'est le signe d'un vol — toute la
 * famille de jetons est alors révoquée (détection de réutilisation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('tenant_id')->nullable()->index();

            // SHA-256 du jeton : jamais le jeton en clair.
            $table->string('token_hash', 64)->unique();

            // Identifiant de lignée : permet de tout révoquer d'un coup en
            // cas de détection de réutilisation.
            $table->uuid('family_id')->index();

            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            // Traçabilité pour l'analyse d'incident.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
