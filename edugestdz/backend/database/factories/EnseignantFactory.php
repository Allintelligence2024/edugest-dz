<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EnseignantFactory extends Factory
{
    private static int $seq = 1;

    public function definition(): array
    {
        $annee    = now()->year;
        $contrats = ['CDI', 'CDD', 'vacataire', 'freelance'];

        return [
            'matricule'     => sprintf('ENS-%d-%03d', $annee, self::$seq++),
            'nom'           => strtoupper($this->faker->lastName()),
            'prenom'        => $this->faker->firstName(),
            'sexe'          => $this->faker->randomElement(['M', 'F']),
            'telephone'     => '0' . $this->faker->numerify('5########'),
            'email'         => $this->faker->unique()->safeEmail(),
            'type_contrat'  => $this->faker->randomElement($contrats),
            'salaire_base'  => $this->faker->numberBetween(35000, 80000),
            'taux_horaire'  => $this->faker->numberBetween(800, 2000),
            'statut'        => 'actif',
            'date_embauche' => $this->faker->dateTimeBetween('-3 years', 'now'),
            'wilaya_id'     => 16,
        ];
    }
}
