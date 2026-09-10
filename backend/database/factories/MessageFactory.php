<?php
namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'expediteur_id'   => User::factory(),
            'message'         => $this->faker->paragraph(),
            'type_message'    => 'texte',
        ];
    }
}
