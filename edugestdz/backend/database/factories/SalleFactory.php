<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SalleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom'       => $this->faker->unique()->word() . ' ' . $this->faker->randomLetter(),
            'capacite'  => $this->faker->numberBetween(5, 50),
            'statut'    => 'disponible',
        ];
    }
}
