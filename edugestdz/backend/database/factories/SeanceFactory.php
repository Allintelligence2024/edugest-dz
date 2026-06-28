<?php
namespace Database\Factories;

use App\Models\Cours;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cours_id'    => Cours::factory(),
            'date_seance' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'heure_debut' => $this->faker->randomElement(['08:00','09:00','10:00','13:00','14:00']),
            'heure_fin'   => $this->faker->randomElement(['10:00','11:00','12:00','15:00','16:00']),
            'statut'      => $this->faker->randomElement(['planifiee', 'terminee', 'annulee']),
            'motif_annulation' => null,
        ];
    }

    public function terminee(): static
    {
        return $this->state(fn(array $a) => ['statut' => 'terminée']);
    }

    public function planifiee(): static
    {
        return $this->state(fn(array $a) => ['statut' => 'planifiée']);
    }
}
