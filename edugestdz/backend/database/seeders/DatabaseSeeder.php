<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WilayaSeeder::class,
            CommuneSeeder::class,
            RolePermissionSeeder::class,
            TestUserSeeder::class,
            CurriculumAlgerienSeeder::class,
        ]);
    }
}
