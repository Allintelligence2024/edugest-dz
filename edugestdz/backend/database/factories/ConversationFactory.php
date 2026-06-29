<?php
namespace Database\Factories;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'      => Tenant::factory(),
            'sujet'          => $this->faker->sentence(4),
            'participants'   => [],
            'lu_par'         => [],
            'last_message_at' => null,
        ];
    }
}
