<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Tenant, User};
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'nom'             => 'Centre Alpha',
            'slug'            => 'centre-alpha',
            'email'           => 'contact@centrealpha.dz',
            'telephone'       => '0550123456',
            'statut'          => 'actif',
            'date_expiration' => now()->addYear(),
            'wilaya_id'       => 16,
            'commune_id'      => 1,
            'adresse'         => '16000 Alger',
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'nom'       => 'Admin',
            'prenom'    => 'Centre',
            'email'     => 'admin@edugest.dz',
            'password'  => Hash::make('password'),
            'telephone' => '0550123456',
            'langue'    => 'fr',
            'role_id'   => 2,
            'is_actif'  => true,
        ]);
        // Role already assigned via role_id = 2 (admin)
    }
}
