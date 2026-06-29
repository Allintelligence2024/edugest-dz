<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FactureFactory extends Factory
{
    private static int $seq = 1;

    public function definition(): array
    {
        $mois = $this->faker->numberBetween(1, 12);
        $annee = now()->year;
        $sousTotal = $this->faker->randomFloat(2, 2000, 50000);

        return [
            'eleve_id'        => \App\Models\Eleve::factory(),
            'numero_facture'  => sprintf('FAC-%d-%04d', $annee, self::$seq++),
            'mois'            => $mois,
            'annee'           => $annee,
            'date_emission'   => now()->toDateString(),
            'date_echeance'   => now()->addDays(30)->toDateString(),
            'sous_total'      => $sousTotal,
            'remise_pct'      => 0,
            'remise_montant'  => 0,
            'total_ttc'       => $sousTotal,
            'statut'          => 'émise',
        ];
    }
}
