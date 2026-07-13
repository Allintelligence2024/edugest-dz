<?php

namespace Database\Factories;

use App\Models\{TrancheFractionnement, PlanFractionnement};
use Illuminate\Database\Eloquent\Factories\Factory;

class TrancheFractionnementFactory extends Factory
{
    protected $model = TrancheFractionnement::class;

    public function definition(): array
    {
        $plan = PlanFractionnement::factory()->create([
            'tenant_id' => config('tenant.current_id', 'test-tenant'),
        ]);

        return [
            'tenant_id' => config('tenant.current_id', 'test-tenant'),
            'plan_id' => $plan->id,
            'numero' => $this->faker->numberBetween(1, 4),
            'montant' => $this->faker->randomFloat(2, 5000, 100000),
            'date_echeance' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'statut' => 'en_attente',
            'montant_paye' => 0,
        ];
    }
}
