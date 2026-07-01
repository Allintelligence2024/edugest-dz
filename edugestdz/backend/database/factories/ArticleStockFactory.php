<?php

namespace Database\Factories;

use App\Models\ArticleStock;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleStockFactory extends Factory
{
    protected $model = ArticleStock::class;

    public function definition(): array
    {
        $categories = [
            'mobilier', 'fourniture_bureau', 'fourniture_pedagogique',
            'equipement_informatique', 'materiel_entretien',
        ];
        $unites = ['pièce', 'unité', 'rame', 'boîte', 'lot'];

        return [
            'nom'              => $this->faker->words(3, true),
            'categorie'        => $this->faker->randomElement($categories),
            'unite'            => $this->faker->randomElement($unites),
            'quantite_stock'   => $this->faker->numberBetween(1, 50),
            'quantite_minimum' => $this->faker->numberBetween(1, 5),
            'etat'             => $this->faker->randomElement(['bon', 'use', 'bon']),
            'valeur_unitaire'  => $this->faker->numberBetween(500, 50000),
            'est_immobilise'   => false,
            'actif'            => true,
        ];
    }

    public function enAlerte(): static
    {
        return $this->state([
            'quantite_stock'   => 0,
            'quantite_minimum' => 5,
        ]);
    }

    public function mobilier(): static
    {
        return $this->state([
            'categorie'     => 'mobilier',
            'est_immobilise'=> true,
            'unite'         => 'pièce',
        ]);
    }
}
