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

        if (in_array(config('app.env'), ['local', 'staging'])) {
            $this->call([
                EcoleDemoSeeder::class,
            ]);
        }
    }
}
