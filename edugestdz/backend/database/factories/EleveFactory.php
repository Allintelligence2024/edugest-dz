<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EleveFactory extends Factory
{
    private static int $seq = 1;

    public function definition(): array
    {
        $niveaux = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS'];
        $annee   = now()->year;

        return [
            'numero_inscription' => sprintf('EL-%d-%04d', $annee, self::$seq++),
            'nom'                => strtoupper($this->faker->lastName()),
            'prenom'             => $this->faker->firstName(),
            'date_naissance'     => $this->faker->dateTimeBetween('-18 years', '-8 years'),
            'sexe'               => $this->faker->randomElement(['M', 'F']),
            'niveau_scolaire'    => $this->faker->randomElement($niveaux),
            'statut'             => 'actif',
            'wilaya_id'          => $this->faker->numberBetween(1, 48),
            'nationalite'        => 'Algérienne',
        ];
    }
}
