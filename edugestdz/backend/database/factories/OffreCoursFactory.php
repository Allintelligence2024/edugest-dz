<?php

namespace Database\Factories;

use App\Models\OffreCours;
use Illuminate\Database\Eloquent\Factories\Factory;

class OffreCoursFactory extends Factory
{
    protected $model = OffreCours::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => \Illuminate\Support\Str::uuid(),
            'titre'        => $this->faker->sentence(4),
            'matiere'      => $this->faker->randomElement(['Mathématiques','Physique','Français','Anglais']),
            'niveaux'      => ['1AS','2AS'],
            'type'         => $this->faker->randomElement(['individuel','groupe','en_ligne']),
            'tarif_heure'  => $this->faker->randomFloat(2, 400, 1500),
            'duree_seance' => 60,
            'essai_gratuit'=> false,
            'active'       => true,
        ];
    }
}
