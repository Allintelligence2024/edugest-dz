<?php

namespace Database\Factories;

use App\Models\InterventionEntretien;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterventionEntretienFactory extends Factory
{
    protected $model = InterventionEntretien::class;

    public function definition(): array
    {
        return [
            'titre'            => $this->faker->sentence(5),
            'description'      => $this->faker->paragraph(),
            'type'             => $this->faker->randomElement(['panne', 'degradation', 'entretien_preventif']),
            'priorite'         => $this->faker->randomElement(['urgente', 'haute', 'normale', 'basse']),
            'statut'           => 'signale',
            'date_signalement' => today()->toDateString(),
        ];
    }

    public function urgente(): static
    {
        return $this->state(['priorite' => 'urgente']);
    }

    public function resolue(): static
    {
        return $this->state([
            'statut'          => 'resolu',
            'date_resolution' => today()->toDateString(),
            'cout_reel'       => $this->faker->numberBetween(5000, 100000),
        ]);
    }
}
