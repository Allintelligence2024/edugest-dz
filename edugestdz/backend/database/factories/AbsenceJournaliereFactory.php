<?php

namespace Database\Factories;

use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsenceJournaliereFactory extends Factory
{
    protected $model = AbsenceJournaliere::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => \Illuminate\Support\Str::uuid(),
            'eleve_id'         => Eleve::factory(),
            'date_absence'     => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'statut'           => $this->faker->randomElement(['non_justifiée', 'justifiée', 'en_attente']),
            'motif'            => $this->faker->optional()->sentence(),
            'sms_parent_envoye'=> false,
        ];
    }

    public function nonJustifiee(): static
    {
        return $this->state(['statut' => 'non_justifiée']);
    }

    public function justifiee(): static
    {
        return $this->state(['statut' => 'justifiée']);
    }
}
