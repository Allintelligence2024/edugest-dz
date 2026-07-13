<?php

namespace Tests\Feature\Api;

use App\Models\{User, Tenant, Enseignant, Matiere, Groupe, Salle, Cours, Seance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionRemplacantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;
    protected string $seanceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $this->admin  = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => null,
        ]);

        config(['tenant.current_id' => $this->tenant->id]);

        $matiere = Matiere::create(['tenant_id' => $this->tenant->id, 'nom_fr' => 'Mathématiques', 'statut' => 'actif']);
        $groupe  = Groupe::create(['tenant_id' => $this->tenant->id, 'nom' => 'Groupe A', 'niveau_scolaire' => '3eme', 'capacite_max' => 30, 'statut' => 'actif']);
        $salle   = Salle::create(['tenant_id' => $this->tenant->id, 'nom' => 'Salle 10', 'capacite' => 30, 'statut' => 'disponible']);

        $absent = Enseignant::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Benali',
            'prenom'      => 'Ahmed',
            'specialite'  => 'Mathématiques',
            'statut'      => 'actif',
        ]);

        $compatible = Enseignant::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Hadj',
            'prenom'      => 'Sara',
            'specialite'  => 'Mathématiques',
            'statut'      => 'actif',
        ]);
        $compatible->matieres()->attach($matiere->id);

        Enseignant::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Mebarki',
            'prenom'      => 'Karim',
            'specialite'  => 'Physique',
            'statut'      => 'actif',
        ]);

        $cours = Cours::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $absent->id,
            'matiere_id'    => $matiere->id,
            'groupe_id'     => $groupe->id,
            'salle_id'      => $salle->id,
            'jour_semaine'  => 0,
            'heure_debut'   => '08:00',
            'heure_fin'     => '10:00',
            'type_cours'    => 'groupe',
            'recurrence'    => 'hebdo',
            'date_debut'    => now()->toDateString(),
            'statut'        => 'actif',
        ]);

        $this->seanceId = Seance::create([
            'tenant_id'   => $this->tenant->id,
            'cours_id'    => $cours->id,
            'date_seance' => now()->addWeek()->startOfWeek()->addDay(1),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'statut'      => 'planifiée',
        ])->id;
    }

    public function test_retourne_suggestions_ordonnees(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/remplacements/suggestions/{$this->seanceId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.suggestions');

        $suggestions = $response->json('data.suggestions');
        $this->assertGreaterThanOrEqual($suggestions[1]['score'], $suggestions[0]['score']);
    }

    public function test_exclut_enseignant_absent(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/remplacements/suggestions/{$this->seanceId}");

        $noms = collect($response->json('data.suggestions'))->pluck('nom')->toArray();
        $this->assertNotContains('Benali', $noms);
    }

    public function test_exclut_enseignant_en_conflit(): void
    {
        $compatible = Enseignant::where('nom', 'Hadj')->first();
        $groupe     = Groupe::where('nom', 'Groupe A')->first();
        $matiere    = Matiere::where('nom_fr', 'Mathématiques')->first();
        $salle      = Salle::where('nom', 'Salle 10')->first();

        $coursConflit = Cours::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $compatible->id,
            'matiere_id'    => $matiere->id,
            'groupe_id'     => $groupe->id,
            'salle_id'      => $salle->id,
            'jour_semaine'  => 0,
            'heure_debut'   => '08:00',
            'heure_fin'     => '10:00',
            'type_cours'    => 'groupe',
            'recurrence'    => 'hebdo',
            'date_debut'    => now()->toDateString(),
            'statut'        => 'actif',
        ]);

        Seance::create([
            'tenant_id'   => $this->tenant->id,
            'cours_id'    => $coursConflit->id,
            'date_seance' => Seance::find($this->seanceId)->date_seance,
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'statut'      => 'planifiée',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/remplacements/suggestions/{$this->seanceId}");

        $suggestions = $response->json('data.suggestions');
        $hadj = collect($suggestions)->firstWhere('nom', 'Hadj');
        if ($hadj) {
            $this->assertFalse($hadj['disponibilite_ok']);
        }
    }

    public function test_favorise_enseignant_ayant_enseigne_groupe(): void
    {
        $compatible = Enseignant::where('nom', 'Hadj')->first();
        $groupe     = Groupe::where('nom', 'Groupe A')->first();
        $matiere    = Matiere::where('nom_fr', 'Mathématiques')->first();
        $salle      = Salle::where('nom', 'Salle 10')->first();

        Cours::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $compatible->id,
            'matiere_id'    => $matiere->id,
            'groupe_id'     => $groupe->id,
            'salle_id'      => $salle->id,
            'jour_semaine'  => 2,
            'heure_debut'   => '10:00',
            'heure_fin'     => '12:00',
            'type_cours'    => 'groupe',
            'recurrence'    => 'hebdo',
            'date_debut'    => now()->toDateString(),
            'statut'        => 'actif',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/remplacements/suggestions/{$this->seanceId}");

        $suggestions = $response->json('data.suggestions');
        $hadj = collect($suggestions)->firstWhere('nom', 'Hadj');
        $this->assertTrue($hadj['experience_groupe']);
    }

    public function test_seance_inexistante_404(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/remplacements/suggestions/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_limite_resultats(): void
    {
        $matiere = Matiere::where('nom_fr', 'Mathématiques')->first();

        for ($i = 0; $i < 3; $i++) {
            $ens = Enseignant::factory()->create([
                'tenant_id'   => $this->tenant->id,
                'nom'         => "Suppleant{$i}",
                'prenom'      => "Test{$i}",
                'specialite'  => 'Mathématiques',
                'statut'      => 'actif',
            ]);
            $ens->matieres()->attach($matiere->id);
        }

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/remplacements/suggestions/{$this->seanceId}?limit=2");

        $response->assertOk();
        $this->assertLessThanOrEqual(2, count($response->json('data.suggestions')));
    }
}
