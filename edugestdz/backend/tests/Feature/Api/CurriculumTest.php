<?php
namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_algerien_charge(): void
    {
        $this->artisan('db:seed', ['--class' => 'CurriculumAlgerienSeeder']);

        $this->assertDatabaseCount('paliers', 3);
        $this->assertDatabaseCount('branches', 7);
        $this->assertDatabaseCount('niveaux_scolaires', 12);
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Sciences Naturelles',
            'coefficient' => 6,
        ]);
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Mathématiques',
            'coefficient' => 7,
        ]);
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Comptabilité et Management',
            'coefficient' => 6,
        ]);
    }

    public function test_niveaux_sont_dans_le_bon_ordre(): void
    {
        $this->artisan('db:seed', ['--class' => 'CurriculumAlgerienSeeder']);

        $niveaux = \DB::table('niveaux_scolaires')->orderBy('ordre')->pluck('code')->toArray();
        $expected = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS'];
        $this->assertEquals($expected, $niveaux);
    }
}
