<?php

namespace Database\Factories;

use App\Models\{PlanFractionnement, Facture, Eleve};
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFractionnementFactory extends Factory
{
    protected $model = PlanFractionnement::class;

    public function definition(): array
    {
        $facture = Facture::factory()->create([
            'tenant_id' => config('tenant.current_id', 'test-tenant'),
        ]);

        return [
            'tenant_id' => config('tenant.current_id', 'test-tenant'),
            'facture_id' => $facture->id,
            'eleve_id' => $facture->eleve_id,
            'nb_tranches' => $this->faker->randomElement([2, 3, 4]),
            'montant_total' => $facture->total_ttc,
            'statut' => 'actif',
        ];
    }
}
