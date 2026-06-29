<?php
namespace Database\Factories;

use App\Models\Groupe;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'       => Tenant::factory(),
            'groupe_id'       => Groupe::factory(),
            'titre'           => $this->faker->sentence(3),
            'type_eval'       => $this->faker->randomElement(['devoir_classe','devoir_maison','test_rapide','examen_mensuel','examen_module']),
            'date_evaluation' => $this->faker->date(),
            'note_sur'        => 20,
            'coefficient'     => $this->faker->randomFloat(2, 0.5, 5),
            'trimestre'       => $this->faker->randomElement(['T1','T2','T3']),
            'description'     => null,
        ];
    }
}
