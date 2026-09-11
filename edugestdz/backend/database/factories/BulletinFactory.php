<?php
namespace Database\Factories;

use App\Models\Eleve;
use App\Models\Groupe;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulletinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'eleve_id'         => Eleve::factory(),
            'groupe_id'        => Groupe::factory(),
            'trimestre'        => $this->faker->randomElement(['T1', 'T2', 'T3']),
            'annee_scolaire'   => '2025/2026',
            'moyenne_generale' => $this->faker->randomFloat(2, 0, 20),
            'rang'             => $this->faker->numberBetween(1, 30),
            'effectif_classe'  => 30,
        ];
    }
}
