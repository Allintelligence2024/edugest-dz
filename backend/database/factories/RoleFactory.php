<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom'         => $this->faker->randomElement(['admin', 'secretaire', 'enseignant', 'parent', 'eleve']),
            'label_fr'    => $this->faker->word(),
            'label_ar'    => $this->faker->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}
