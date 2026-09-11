<?php
namespace Database\Factories;

use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'evaluation_id' => Evaluation::factory(),
            'eleve_id'      => Eleve::factory(),
            'note'          => $this->faker->randomFloat(2, 0, 20),
            'absent'        => false,
        ];
    }
}
