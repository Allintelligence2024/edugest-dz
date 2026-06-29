<?php
namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampagneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'  => Tenant::factory(),
            'titre'      => $this->faker->sentence(3),
            'message'    => $this->faker->paragraph(),
            'canaux'     => ['in_app'],
            'statut'     => 'brouillon',
            'cree_par'   => User::factory(),
        ];
    }
}
