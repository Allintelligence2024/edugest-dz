<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaiementFactory extends Factory
{
    private static int $seq = 1;

    public function definition(): array
    {
        return [
            'facture_id'     => \App\Models\Facture::factory(),
            'montant'        => $this->faker->randomFloat(2, 1000, 30000),
            'mode_paiement'  => $this->faker->randomElement(['espèces', 'cib', 'dahabia', 'baridimob', 'virement', 'chèque']),
            'date_paiement'  => now()->toDateString(),
            'reference_trans' => strtoupper($this->faker->bothify('REF-####-????')),
            'statut'         => 'confirmé',
        ];
    }
}
