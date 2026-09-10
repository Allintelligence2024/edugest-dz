<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'token'    => $this->faker->sha256(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
        ];
    }
}
