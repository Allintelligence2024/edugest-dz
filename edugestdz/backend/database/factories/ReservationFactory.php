<?php
namespace Database\Factories;

use App\Models\{OffrePublique, Eleve};
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'offre_id'   => OffrePublique::factory(),
            'eleve_id'   => Eleve::factory(),
            'statut'     => 'en_attente',
            'montant'    => $this->faker->numberBetween(1000, 10000),
            'commission' => $this->faker->numberBetween(50, 700),
            'date_debut' => $this->faker->dateTimeBetween('now', '+1 month'),
            'message'    => $this->faker->optional()->sentence(),
        ];
    }

    public function payee(): static
    {
        return $this->state(fn(array $attrs) => [
            'statut'  => 'payee',
            'mode_paiement' => $this->faker->randomElement(['cib', 'dahabia', 'baridimob']),
        ]);
    }

    public function terminee(): static
    {
        return $this->payee()->state(fn(array $attrs) => [
            'statut' => 'terminee',
        ]);
    }
}
