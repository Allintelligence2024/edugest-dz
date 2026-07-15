<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            DB::table('tenant_modules')->updateOrCreate(
                ['tenant_id' => $tenantId, 'module_key' => 'marketplace'],
                [
                    'actif'        => false,
                    'desactive_le' => now(),
                    'raison'       => 'Désactivé par défaut — nécessite profil public + activation explicite',
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('tenant_modules')
            ->where('module_key', 'marketplace')
            ->update(['actif' => true, 'desactive_le' => null, 'raison' => null]);
    }
};
