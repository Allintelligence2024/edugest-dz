<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom'      => $this->faker->lastName(),
            'prenom'   => $this->faker->firstName(),
            'email'    => $this->faker->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'statut'   => 'actif',
            'langue'   => 'fr',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
