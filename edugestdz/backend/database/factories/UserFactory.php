<?php
namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id'  => Tenant::factory(),
            'nom'        => $this->faker->lastName(),
            'prenom'     => $this->faker->firstName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => static::$password ??= Hash::make('password'),
            'statut'     => 'actif',
            'langue'     => 'fr',
            'role_id'    => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
