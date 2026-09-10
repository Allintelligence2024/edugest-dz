<?php

namespace Database\Factories;

use App\Models\ArretBus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArretBusFactory extends Factory
{
    protected $model = ArretBus::class;

    public function definition(): array
    {
        return [
            'nom'         => 'Arrêt ' . $this->faker->streetName(),
            'adresse'     => $this->faker->address(),
            'wilaya'      => $this->faker->numberBetween(1, 58),
            'ordre'       => $this->faker->unique()->numberBetween(1, 20),
            'heure_matin' => $this->faker->time('H:i'),
            'heure_soir'  => $this->faker->time('H:i'),
            'actif'       => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
