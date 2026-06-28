<?php
namespace Database\Factories;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupeFactory extends Factory
{
    public function definition(): array
    {
        $niveaux = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'];

        return [
            'matiere_id'      => Matiere::factory(),
            'nom'             => $this->faker->unique()->bothify('Groupe ##-??'),
            'niveau_scolaire' => $this->faker->randomElement($niveaux),
            'capacite_max'    => $this->faker->numberBetween(8, 25),
            'statut'          => 'actif',
            'description'     => $this->faker->sentence(),
        ];
    }
}
