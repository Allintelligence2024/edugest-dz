<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitialProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WilayaSeeder::class,
            CommuneSeeder::class,
            RolePermissionSeeder::class,
            CurriculumAlgerienSeeder::class,
        ]);

        $tenantId = Str::uuid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'nom_etablissement' => 'EduGest DZ',
            'slug' => 'edugest-dz',
            'type_etablissement' => 'centre_cours',
            'wilaya_id' => 16,
            'email' => 'contact@edugest.dz',
            'telephone' => '0550000000',
            'plan_abonnement' => 'professionnel',
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::table('roles')->where('nom', 'super_admin')->value('id');
        $adminId = Str::uuid();
        DB::table('users')->insert([
            'id' => $adminId,
            'tenant_id' => $tenantId,
            'role_id' => $adminRoleId,
            'nom' => 'Admin',
            'prenom' => 'EduGest',
            'email' => 'admin@edugest.dz',
            'password' => bcrypt(Str::random(32)),
            'statut' => 'actif',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
