<?php

namespace Database\Factories;

use App\Models\InscriptionCantine;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscriptionCantineFactory extends Factory
{
    protected $model = InscriptionCantine::class;

    public function definition(): array
    {
        return [
            'type_abonnement' => 'mensuel',
            'regime'          => $this->faker->randomElement(['normal', 'sans_porc', 'vegetarien']),
            'actif'           => true,
            'date_debut'      => now()->startOfMonth()->toDateString(),
            'tarif_mensuel'   => $this->faker->numberBetween(2000, 5000),
        ];
    }

    public function sansPorc(): static
    {
        return $this->state(['regime' => 'sans_porc']);
    }

    public function vegetarien(): static
    {
        return $this->state(['regime' => 'vegetarien']);
    }
}
