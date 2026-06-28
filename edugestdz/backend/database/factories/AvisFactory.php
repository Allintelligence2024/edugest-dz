<?php
namespace Database\Factories;

use App\Models\{Reservation, Eleve, Enseignant};
use Illuminate\Database\Eloquent\Factories\Factory;

class AvisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->terminee(),
            'eleve_id'       => Eleve::factory(),
            'enseignant_id'  => Enseignant::factory(),
            'note'           => $this->faker->numberBetween(1, 5),
            'commentaire'    => $this->faker->optional(0.7)->sentence(8),
        ];
    }
}
