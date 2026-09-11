<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        $nom = $this->faker->company();
        return [
            'nom_etablissement' => $nom,
            'slug'              => Str::slug($nom) . '-' . Str::random(4),
            'type_etablissement'=> $this->faker->randomElement(['centre_cours', 'ecole_privee', 'formation']),
            'telephone'         => '0' . $this->faker->numerify('5########'),
            'email'             => $this->faker->unique()->companyEmail(),
            'plan_abonnement'   => 'pro',
            'statut'            => 'actif',
            'date_expiration'   => now()->addYear(),
            'wilaya_id'         => 16,
        ];
    }
}
