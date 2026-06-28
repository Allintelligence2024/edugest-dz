<?php
namespace Database\Factories;

use App\Models\{Enseignant, Matiere};
use Illuminate\Database\Eloquent\Factories\Factory;

class OffrePubliqueFactory extends Factory
{
    public function definition(): array
    {
        $niveaux = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'];

        return [
            'type_offre'      => $this->faker->randomElement(['enseignant', 'centre']),
            'matiere_id'      => Matiere::factory(),
            'niveau'          => $this->faker->randomElement($niveaux),
            'tarif_seance'    => $this->faker->numberBetween(500, 5000),
            'tarif_mensuel'   => $this->faker->optional()->numberBetween(3000, 20000),
            'type_cours'      => $this->faker->randomElement(['presentiel', 'en_ligne', 'les_deux']),
            'wilaya_id'       => $this->faker->numberBetween(1, 58),
            'adresse'         => $this->faker->optional()->address(),
            'capacite_max'    => $this->faker->numberBetween(1, 30),
            'places_restantes'=> $this->faker->numberBetween(0, 30),
            'description'     => $this->faker->optional()->sentence(12),
            'statut'          => 'active',
        ];
    }

    public function forEnseignant(?string $enseignantId = null): static
    {
        return $this->state(fn(array $attrs) => [
            'type_offre'    => 'enseignant',
            'enseignant_id' => $enseignantId ?? Enseignant::factory(),
        ]);
    }
}
