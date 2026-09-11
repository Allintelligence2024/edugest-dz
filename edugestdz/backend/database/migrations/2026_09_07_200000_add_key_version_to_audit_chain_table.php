<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 — Rotation de la clé de signature de la chaîne d'audit.
 *
 * Avant : la signature HMAC utilisait config('app.key'). Toute rotation de
 * APP_KEY (procédure de sécurité normale, ex. après le départ d'un
 * administrateur) invalidait rétroactivement TOUS les blocs — la chaîne
 * cessait de pouvoir prouver sa propre intégrité, ce pour quoi elle existe.
 *
 * Après : clé dédiée AUDIT_CHAIN_KEY, et chaque bloc mémorise la version de
 * clé qui l'a signé. On peut donc faire tourner la clé sans perdre la
 * vérifiabilité de l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_chain', function (Blueprint $table) {
            $table->unsignedSmallInteger('key_version')->default(1)->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('audit_chain', function (Blueprint $table) {
            $table->dropColumn('key_version');
        });
    }
};
