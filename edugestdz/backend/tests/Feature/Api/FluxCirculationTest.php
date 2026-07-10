<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Illuminate\Support\Str;

class FluxCirculationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $admin;
    private User   $enseignant;
    private User   $eleveUser;
    private Eleve  $eleve;
    private string $tokenAdmin;
    private string $tokenEnseignant;
    private string $tokenEleve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $roleAdmin = Role::factory()->create(['nom' => 'admin']);
        $roleEns   = Role::factory()->create(['nom' => 'enseignant']);
        $roleEleve = Role::factory()->create(['nom' => 'eleve']);

        $this->admin      = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleAdmin->id,
        ]);
        $this->enseignant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleEns->id,
        ]);
        $this->eleveUser  = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleEleve->id,
        ]);
        $this->eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->eleveUser->id,
        ]);

        $this->tokenAdmin      = JWTAuth::fromUser($this->admin);
        $this->tokenEnseignant = JWTAuth::fromUser($this->enseignant);
        $this->tokenEleve      = JWTAuth::fromUser($this->eleveUser);
    }

    // ── Absence Enseignant ─────────────────────────────────────────────

    public function test_enseignant_peut_signaler_son_absence(): void
    {
        $this->withToken($this->tokenEnseignant)
            ->postJson('/api/v1/absences-enseignants', [
                'date_absence' => now()->addDay()->toDateString(),
                'motif'        => 'Rendez-vous m\u00e9dical',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_absence_signalement_sans_doublon(): void
    {
        $date = now()->addDays(2)->toDateString();

        $this->withToken($this->tokenEnseignant)
            ->postJson('/api/v1/absences-enseignants', ['date_absence' => $date]);

        $this->withToken($this->tokenEnseignant)
            ->postJson('/api/v1/absences-enseignants', ['date_absence' => $date])
            ->assertStatus(201);
    }

    public function test_admin_peut_lister_absences(): void
    {
        $this->withToken($this->tokenAdmin)
            ->getJson('/api/v1/absences-enseignants')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── Devoirs ────────────────────────────────────────────────────────

    public function test_devoir_necessaire_cours_valide(): void
    {
        $this->withToken($this->tokenEnseignant)
            ->postJson('/api/v1/devoirs', [
                'cours_id'    => (string) Str::uuid(),
                'titre'       => 'Exercices page 45',
                'date_remise' => now()->addWeek()->toDateString(),
            ])
            ->assertStatus(404);
    }

    public function test_eleve_peut_voir_ses_devoirs(): void
    {
        $this->withToken($this->tokenEleve)
            ->getJson('/api/v1/devoirs')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── Feedback P\u00e9dagogique ────────────────────────────────────────

    public function test_eleve_soumet_feedback(): void
    {
        $this->withToken($this->tokenEleve)
            ->postJson('/api/v1/feedbacks-pedagogiques', [
                'enseignant_user_id' => $this->enseignant->id,
                'trimestre'          => 3,
                'note_qualite'       => 4,
                'type_feedback'      => 'pedagogie',
                'commentaire'        => 'Cours bien expliqu\u00e9 mais rythme rapide.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_feedback_doublon_refuse(): void
    {
        $payload = [
            'enseignant_user_id' => $this->enseignant->id,
            'trimestre'          => 3,
            'note_qualite'       => 4,
            'type_feedback'      => 'pedagogie',
        ];

        $this->withToken($this->tokenEleve)
            ->postJson('/api/v1/feedbacks-pedagogiques', $payload)
            ->assertStatus(201);

        $this->withToken($this->tokenEleve)
            ->postJson('/api/v1/feedbacks-pedagogiques', $payload)
            ->assertStatus(422);
    }

    public function test_admin_voit_feedbacks(): void
    {
        $this->withToken($this->tokenAdmin)
            ->getJson('/api/v1/feedbacks-pedagogiques')
            ->assertStatus(200);
    }

    public function test_enseignant_ne_peut_pas_voir_feedbacks(): void
    {
        $this->withToken($this->tokenEnseignant)
            ->getJson('/api/v1/feedbacks-pedagogiques')
            ->assertStatus(403);
    }

    // ── Signalement Grave ──────────────────────────────────────────────

    public function test_eleve_soumet_signalement_grave(): void
    {
        $response = $this->withToken($this->tokenEleve)
            ->postJson('/api/v1/signalements-graves', [
                'type_incident' => 'violence_verbale',
                'gravite'       => 'grave',
                'description'   => "L'enseignant a utilis\u00e9 des propos irrespectueux envers moi lors du cours du 10 juillet.",
                'date_incident' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['numero_ticket', 'delai_reponse']);
    }

    public function test_enseignant_ne_peut_pas_voir_signalements(): void
    {
        $this->withToken($this->tokenEnseignant)
            ->getJson('/api/v1/signalements-graves')
            ->assertStatus(403);
    }

    public function test_admin_voit_signalements(): void
    {
        $this->withToken($this->tokenAdmin)
            ->getJson('/api/v1/signalements-graves')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_description_signalement_minimum_20_caracteres(): void
    {
        $this->withToken($this->tokenEleve)
            ->postJson('/api/v1/signalements-graves', [
                'type_incident' => 'autre',
                'gravite'       => 'important',
                'description'   => 'Court',
                'date_incident' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }
}
