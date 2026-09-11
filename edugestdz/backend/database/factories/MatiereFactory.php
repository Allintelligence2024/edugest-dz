<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MatiereFactory extends Factory
{
    private static array $matieres = [
        ['fr' => 'Mathématiques', 'ar' => 'الرياضيات'],
        ['fr' => 'Physique',      'ar' => 'الفيزياء'],
        ['fr' => 'Français',      'ar' => 'الفرنسية'],
        ['fr' => 'Anglais',       'ar' => 'الإنجليزية'],
        ['fr' => 'Arabe',         'ar' => 'العربية'],
        ['fr' => 'SVT',           'ar' => 'علوم الطبيعة والحياة'],
        ['fr' => 'Histoire',      'ar' => 'التاريخ'],
        ['fr' => 'Philosophie',   'ar' => 'الفلسفة'],
        ['fr' => 'Informatique',  'ar' => 'الإعلام الآلي'],
    ];
    private static int $index = 0;

    public function definition(): array
    {
        $m = self::$matieres[self::$index % count(self::$matieres)];
        self::$index++;

        return [
            'nom_fr'      => $m['fr'],
            'nom_ar'      => $m['ar'],
            'couleur'     => $this->faker->hexColor(),
            'description' => $this->faker->sentence(),
            'statut'      => 'actif',
        ];
    }
}
