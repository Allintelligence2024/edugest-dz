<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG#30 — La vérification des QR codes de présence était non fonctionnelle.
 *
 * Avant : le jeton était un hash bcrypt calculé sur un payload contenant
 * `now()->timestamp`, puis « vérifié » en reconstruisant le payload avec
 * `updated_at->timestamp` — deux valeurs différentes, donc Hash::check()
 * échouait systématiquement. De plus la colonne `qr_code` stocke un CHEMIN
 * d'image, pas le jeton : la vérification scannait donc TOUS les élèves du
 * tenant (O(N)) pour ne jamais trouver de correspondance.
 *
 * Après : on stocke le HMAC-SHA256 du jeton dans une colonne indexée dédiée,
 * ce qui rend la vérification O(1) et déterministe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            // HMAC-SHA256 hexadécimal = 64 caractères.
            $table->string('qr_token_hash', 64)->nullable()->after('qr_code');

            // Version du badge : incrémentée pour révoquer un QR perdu/volé
            // sans avoir à changer la clé de signature globale.
            $table->unsignedInteger('qr_version')->default(1)->after('qr_token_hash');

            $table->timestamp('qr_generated_at')->nullable()->after('qr_version');

            // Unique par tenant : deux établissements ne peuvent pas entrer en
            // collision, et le lookup de scan devient un index scan.
            $table->unique(['tenant_id', 'qr_token_hash'], 'eleves_tenant_qr_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropUnique('eleves_tenant_qr_token_unique');
            $table->dropColumn(['qr_token_hash', 'qr_version', 'qr_generated_at']);
        });
    }
};
