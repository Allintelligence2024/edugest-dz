<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'rembourse_le')) {
                $table->timestamp('rembourse_le')->nullable()->after('recu_par');
            }
            if (!Schema::hasColumn('paiements', 'motif_remboursement')) {
                $table->string('motif_remboursement', 300)->nullable()->after('rembourse_le');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            foreach (['rembourse_le', 'motif_remboursement'] as $col) {
                if (Schema::hasColumn('paiements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
