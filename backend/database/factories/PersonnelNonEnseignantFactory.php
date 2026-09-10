<?php

namespace Database\Factories;

use App\Models\PersonnelNonEnseignant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonnelNonEnseignantFactory extends Factory
{
    protected $model = PersonnelNonEnseignant::class;

    public function definition(): array
    {
        $postes = [
            'femme_menage', 'surveillant', 'chauffeur',
            'secretaire', 'agent_securite', 'technicien',
        ];

        return [
            'nom'           => strtoupper($this->faker->lastName()),
            'prenom'        => $this->faker->firstName(),
            'poste'         => $this->faker->randomElement($postes),
            'type_contrat'  => $this->faker->randomElement(['CDI', 'CDD', 'vacataire']),
            'date_embauche' => $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'salaire_base'  => $this->faker->numberBetween(25000, 80000),
            'telephone'     => '0' . $this->faker->numberBetween(5, 7) . $this->faker->numerify('########'),
            'statut'        => 'actif',
            'matricule'     => strtoupper($this->faker->bothify('AG-####-###')),
        ];
    }

    public function femme_menage(): static
    {
        return $this->state(['poste' => 'femme_menage']);
    }

    public function surveillant(): static
    {
        return $this->state(['poste' => 'surveillant']);
    }

    public function chauffeur(): static
    {
        return $this->state(['poste' => 'chauffeur']);
    }
}
