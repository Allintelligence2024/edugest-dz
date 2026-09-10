<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'order_id')) {
                $table->string('order_id')->nullable()->after('reference_trans')
                      ->comment('ID commande Satim');
            }
            if (!Schema::hasColumn('paiements', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('order_id')
                      ->comment('Payload brut Satim');
            }
            if (!Schema::hasColumn('paiements', 'mode')) {
                $table->string('mode')->default('standard')->after('raw_payload')
                      ->comment('standard|en_ligne');
            }
            if (!Schema::hasColumn('paiements', 'type_paiement')) {
                $table->string('type_paiement')->nullable()->after('mode')
                      ->comment('cib|dahabia|baridimob');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $columns = ['order_id', 'raw_payload', 'mode', 'type_paiement'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('paiements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
