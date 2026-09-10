<?php

namespace Database\Factories;

use App\Models\MenuCantine;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCantineFactory extends Factory
{
    protected $model = MenuCantine::class;

    public function definition(): array
    {
        $plats = ['Poulet roti', 'Couscous', 'Tajine', 'Lentilles', 'Sardines grillees', 'Boeuf aux legumes'];
        $date = $this->faker->unique()->dateTimeBetween('now', '+30 days');
        return [
            'date_repas'         => $date->format('Y-m-d'),
            'type_repas'         => 'dejeuner',
            'plat_principal'     => $this->faker->randomElement($plats),
            'accompagnement'     => $this->faker->randomElement(['Riz', 'Semoule', 'Frites', 'Legumes vapeur']),
            'dessert'            => $this->faker->randomElement(['Fruit', 'Yaourt', 'Cake', null]),
            'boisson'            => 'Eau',
            'prix_unitaire'      => $this->faker->numberBetween(150, 400),
            'nb_couverts_prevus' => $this->faker->numberBetween(20, 80),
            'publie'             => true,
        ];
    }
}
