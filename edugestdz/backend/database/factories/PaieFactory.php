<?php
namespace Database\Factories;

use App\Models\Enseignant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'          => Tenant::factory(),
            'enseignant_id'      => Enseignant::factory(),
            'mois'               => $this->faker->numberBetween(1, 12),
            'annee'              => 2026,
            'salaire_base'       => $this->faker->randomFloat(2, 30000, 80000),
            'heures_travaillees' => $this->faker->randomFloat(2, 80, 160),
            'taux_horaire'       => $this->faker->randomFloat(2, 500, 1500),
            'primes'             => 0,
            'retenues_absences'  => 0,
            'irg'                => 0,
            'cnas'               => 0,
            'casnos'             => 0,
            'salaire_net'        => $this->faker->randomFloat(2, 30000, 80000),
            'statut'             => $this->faker->randomElement(['calculé', 'validé', 'payé']),
        ];
    }
}
