<?php

namespace Database\Factories;

use App\Models\Depense;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepenseFactory extends Factory
{
    protected $model = Depense::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-6 months', 'now');
        $categories = [
            'salaires_enseignants', 'salaires_personnel', 'loyer',
            'electricite_gaz', 'fournitures_bureau', 'assurance', 'autres',
        ];

        return [
            'categorie'     => $this->faker->randomElement($categories),
            'libelle'       => $this->faker->sentence(4),
            'montant'       => $this->faker->randomFloat(2, 1000, 150000),
            'date_depense'  => $date->format('Y-m-d'),
            'mois'          => (int) $date->format('m'),
            'annee'         => (int) $date->format('Y'),
            'fournisseur'   => $this->faker->company(),
            'mode_paiement' => $this->faker->randomElement(['cash', 'virement', 'cheque']),
            'statut'        => 'validee',
        ];
    }

    public function loyer(): static
    {
        return $this->state([
            'categorie' => 'loyer',
            'libelle'   => 'Loyer mensuel local commercial',
            'montant'   => 80000,
        ]);
    }

    public function salaires(): static
    {
        return $this->state([
            'categorie' => 'salaires_enseignants',
            'libelle'   => 'Paie mensuelle enseignants',
        ]);
    }
}
