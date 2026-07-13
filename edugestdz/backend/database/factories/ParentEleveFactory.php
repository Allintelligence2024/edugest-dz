<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ParentEleveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom'          => strtoupper($this->faker->lastName()),
            'prenom'       => $this->faker->firstName(),
            'lien'         => $this->faker->randomElement(['père', 'mère', 'tuteur']),
            'telephone_1'  => $this->faker->numerify('0555555555'),
            'telephone_2'  => null,
            'email'        => $this->faker->safeEmail(),
            'profession'   => $this->faker->jobTitle(),
            'est_urgence'  => false,
        ];
    }
}
