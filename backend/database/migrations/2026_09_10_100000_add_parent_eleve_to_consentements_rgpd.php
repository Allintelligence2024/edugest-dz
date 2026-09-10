<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sprint 6 § 5 — Loi 18-07 : consentement parental.
//
// La table consentements_rgpd (créée en juillet) n'était écrite par AUCUN
// code : le consentement parental n'existait que sur le papier. Impossible
// de PROUVER qu'un parent a consenti au traitement des données de son
// enfant (droit à l'image, sorties, santé…), ce que la loi 18-07 exige
// pour les mineurs.
//
// Sémantique des colonnes :
//   user_id   → qui SAISIT (membre de la direction qui recueille le
//               consentement — trace interne) ;
//   parent_id → qui CONSENT (le titulaire de l'autorité parentale) ;
//   eleve_id  → POUR QUI (l'élève mineur concerné par le consentement).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consentements_rgpd', function (Blueprint $table) {
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');

            $table->uuid('eleve_id')->nullable();
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');

            // Historique de consentement d'un élève / d'un parent.
            $table->index(['tenant_id', 'eleve_id'], 'idx_consent_tenant_eleve');
            $table->index(['tenant_id', 'parent_id'], 'idx_consent_tenant_parent');
        });
    }

    public function down(): void
    {
        Schema::table('consentements_rgpd', function (Blueprint $table) {
            $table->dropIndex('idx_consent_tenant_eleve');
            $table->dropIndex('idx_consent_tenant_parent');
        });

        Schema::table('consentements_rgpd', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['eleve_id']);
            $table->dropColumn(['parent_id', 'eleve_id']);
        });
    }
};
