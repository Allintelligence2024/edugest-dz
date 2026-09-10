<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absences_enseignants')) {
            Schema::create('absences_enseignants', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('enseignant_user_id');
                $table->date('date_absence');
                $table->string('motif', 500)->nullable();
                $table->string('statut')->default('signale');
                $table->uuid('remplacant_user_id')->nullable();
                $table->boolean('eleves_notifies')->default(false);
                $table->boolean('parents_notifies')->default(false);
                $table->timestamp('signale_le')->useCurrent();
                $table->timestamps();

                $table->index(['tenant_id', 'date_absence'], 'idx_abs_ens_tenant_date');
                $table->index(['enseignant_user_id'], 'idx_abs_ens_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absences_enseignants');
    }
};
