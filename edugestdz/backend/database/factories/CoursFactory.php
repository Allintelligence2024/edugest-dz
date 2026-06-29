<?php
namespace Database\Factories;

use App\Models\{Enseignant, Matiere, Groupe};
use Illuminate\Database\Eloquent\Factories\Factory;

class CoursFactory extends Factory
{
    public function definition(): array
    {
        $deb   = $this->faker->randomElement(['08:00','09:00','10:00','13:00','14:00','15:00']);
        $fin   = date('H:i', strtotime($deb) + 3600 * $this->faker->randomElement([1, 1.5, 2]));

        return [
            'enseignant_id' => Enseignant::factory(),
            'matiere_id'    => Matiere::factory(),
            'groupe_id'     => Groupe::factory(),
            'jour_semaine'  => $this->faker->numberBetween(0, 6),
            'heure_debut'   => $deb,
            'heure_fin'     => $fin,
            'type_cours'    => $this->faker->randomElement(['individuel', 'groupe', 'en_ligne']),
            'recurrence'    => 'hebdo',
            'date_debut'    => now()->subMonth(),
            'date_fin'      => now()->addMonths(6),
            'tarif_seance'  => $this->faker->numberBetween(500, 2500),
            'statut'        => 'actif',
        ];
    }
}
