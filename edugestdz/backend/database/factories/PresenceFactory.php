<?php
namespace Database\Factories;

use App\Models\{Seance, Eleve};
use Illuminate\Database\Eloquent\Factories\Factory;

class PresenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seance_id' => Seance::factory(),
            'eleve_id'  => Eleve::factory(),
            'statut'    => $this->faker->randomElement(['présent', 'absent', 'retard', 'excusé']),
            'motif'     => $this->faker->optional()->sentence(),
        ];
    }
}
