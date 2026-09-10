<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'tenants',
        'roles',
        'permissions',
        'matieres',
        'salles',
        'seances',
        'evaluations',
        'notes',
        'presences',
        'bulletins',
        'lignes_facture',
        'contrats',
        'paies',
        'notifications',
        'campagnes',
        'campagne_destinataires',
        'super_admin_actions',
        'device_tokens',
        'avis',
        'groupes',
        'inscriptions',
        'parents',
        'factures',
        'paiements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $tbl) {
                    $tbl->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $tbl) {
                    $tbl->dropSoftDeletes();
                });
            }
        }
    }
};
