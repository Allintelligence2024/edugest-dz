<?php
namespace Tests\Feature\Controllers;
use App\Models\User;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\LmsCours;
use App\Models\LmsChapitre;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsInscription;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LmsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enseignant;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $roleAdmin       = Role::factory()->create(['nom' => 'admin']);
        $roleEnseignant  = Role::factory()->create(['nom' => 'enseignant']);
        $this->admin      = User::factory()->create(['role_id' => $roleAdmin->id, 'tenant_id' => $this->tenant->id]);
        $this->enseignant = User::factory()->create(['role_id' => $roleEnseignant->id, 'tenant_id' => $this->tenant->id]);
    }

    private function makeCours(array $attrs = []): LmsCours
    {
        return LmsCours::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'enseignant_id'   => $this->enseignant->id,
            'titre'           => 'Cours Maths 3AS',
            'matiere'         => 'Mathématiques',
            'niveaux_cibles'  => ['3AS'],
            'langue'          => 'ar',
            'publie'          => true,
            'certificat_actif'=> true,
            'seuil_completion'=> 80,
        ], $attrs));
    }

    public function test_dashboard_lms(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/lms/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total_cours', 'total_inscrits', 'cours_completes', 'certificats']]);
    }

    public function test_lister_cours(): void
    {
        $this->makeCours();
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/lms/cours')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_creer_cours(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/lms/cours', [
                'titre'           => 'Physique Chimie 2AS',
                'matiere'         => 'Physique',
                'niveaux_cibles'  => ['2AS'],
                'langue'          => 'fr',
                'seuil_completion'=> 75,
                'certificat_actif'=> true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.titre', 'Physique Chimie 2AS')
            ->assertJsonPath('data.publie', false);
    }

    public function test_cours_cree_en_brouillon(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/lms/cours', ['titre' => 'Test', 'langue' => 'ar'])
            ->assertStatus(201)
            ->assertJsonPath('data.publie', false);
    }

    public function test_creer_chapitre(): void
    {
        $cours = $this->makeCours();
        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/cours/{$cours->id}/chapitres", [
                'titre' => 'Chapitre 1 : Introduction',
                'ordre' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.titre', 'Chapitre 1 : Introduction');
    }

    public function test_creer_lecon_texte(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id' => $cours->id, 'titre' => 'Ch1', 'ordre' => 1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/chapitres/{$chapitre->id}/lecons", [
                'titre'   => 'Leçon 1 : Les équations',
                'type'    => 'texte',
                'contenu' => '<p>Contenu de la leçon</p>',
                'ordre'   => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'texte');
    }

    public function test_creer_lecon_video(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id' => $cours->id, 'titre' => 'Ch1', 'ordre' => 1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/chapitres/{$chapitre->id}/lecons", [
                'titre'         => 'Vidéo : Résoudre une équation',
                'type'          => 'video',
                'ressource_url' => 'https://www.youtube.com/embed/xxx',
                'duree_minutes' => 12,
                'ordre'         => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'video');
    }

    public function test_publier_cours_sans_lecon_echoue(): void
    {
        $cours = $this->makeCours(['publie' => false]);
        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/cours/{$cours->id}/publier")
            ->assertStatus(422);
    }

    public function test_inscrire_eleve(): void
    {
        $cours = $this->makeCours();
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', [
                'cours_id' => $cours->id,
                'eleve_id' => $eleve->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.cours_id', $cours->id);
    }

    public function test_double_inscription_retourne_meme_record(): void
    {
        $cours = $this->makeCours();
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', ['cours_id' => $cours->id, 'eleve_id' => $eleve->id])
            ->assertStatus(201);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/lms/inscrire', ['cours_id' => $cours->id, 'eleve_id' => $eleve->id])
            ->assertStatus(201);

        $this->assertEquals(1, LmsInscription::where('cours_id', $cours->id)->where('eleve_id', $eleve->id)->count());
    }

    public function test_quiz_correction_automatique(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id' => $cours->id, 'titre' => 'Ch1', 'ordre' => 1]);
        $eleve    = Eleve::factory()->create();
        $insc     = LmsInscription::create(['cours_id' => $cours->id, 'eleve_id' => $eleve->id, 'tenant_id' => $cours->tenant_id]);
        $lecon    = LmsLecon::create(['chapitre_id' => $chapitre->id, 'titre' => 'Quiz', 'type' => 'quiz', 'ordre' => 1]);
        $quiz     = LmsQuiz::create(['lecon_id' => $lecon->id, 'titre' => 'Quiz Test', 'seuil_reussite' => 60, 'nb_tentatives_max' => 3]);
        $question = LmsQuestion::create([
            'quiz_id'  => $quiz->id,
            'type'     => 'qcm',
            'enonce'   => 'Combien font 2+2 ?',
            'options'  => [['id' => 'a', 'texte' => '3', 'correct' => false], ['id' => 'b', 'texte' => '4', 'correct' => true]],
            'points'   => 1,
            'ordre'    => 1,
        ]);
        $quiz->update(['nb_questions' => 1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/quiz/{$quiz->id}/passer", [
                'inscription_id' => $insc->id,
                'reponses'       => [$question->id => 'b'],
                'duree_secondes' => 30,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.reussi', true)
            ->assertJsonPath('data.pourcentage', 100);
    }

    public function test_marquer_lecon_complete(): void
    {
        $cours    = $this->makeCours();
        $chapitre = LmsChapitre::create(['cours_id' => $cours->id, 'titre' => 'Ch1', 'ordre' => 1]);
        $eleve    = Eleve::factory()->create();
        $insc     = LmsInscription::create(['cours_id' => $cours->id, 'eleve_id' => $eleve->id, 'tenant_id' => $cours->tenant_id]);
        $lecon    = LmsLecon::create(['chapitre_id' => $chapitre->id, 'titre' => 'L1', 'type' => 'texte', 'ordre' => 1]);

        $this->actingAs($this->enseignant, 'api')
            ->postJson("/api/v1/lms/inscription/{$insc->id}/lecon/{$lecon->id}/complete", ['temps_secondes' => 120])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['progression_pct', 'cours_complete']]);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/lms/cours')->assertStatus(401);
        $this->getJson('/api/v1/lms/dashboard')->assertStatus(401);
    }
}
