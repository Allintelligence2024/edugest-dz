<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'type'       => $this->faker->randomElement(['info', 'alerte', 'rappel', 'relance']),
            'titre'      => $this->faker->sentence(4),
            'message'    => $this->faker->paragraph(),
            'lien'       => $this->faker->url(),
            'est_lu'     => false,
            'envoye_par' => null,
        ];
    }
}
