<?php

namespace Database\Factories;

use App\Models\CircuitTransport;
use Illuminate\Database\Eloquent\Factories\Factory;

class CircuitTransportFactory extends Factory
{
    protected $model = CircuitTransport::class;

    public function definition(): array
    {
        return [
            'nom'             => 'Circuit ' . $this->faker->randomElement(['Nord', 'Sud', 'Est', 'Ouest', 'Centre']),
            'vehicule_immat'  => $this->faker->bothify('##-???-##'),
            'vehicule_marque' => $this->faker->randomElement(['Toyota', 'Mercedes', 'Peugeot', 'Renault']),
            'capacite'        => $this->faker->numberBetween(15, 40),
            'tarif_mensuel'   => $this->faker->numberBetween(2000, 6000),
            'actif'           => true,
            'type_abonnement' => 'mensuel',
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
