<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Groupe;
use App\Models\Matiere;
use App\Models\Evaluation;
use App\Models\WhatsappMessage;
use App\Models\GoogleClassroomConnexion;
use App\Models\GoogleCourseLiaison;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private Groupe $groupe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp' => [
            'api_token'    => 'test-token',
            'phone_id'     => 'test-phone',
            'api_url'      => 'https://graph.facebook.com/v18.0',
            'verify_token' => 'edugest_verify',
        ]]);

        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->admin = User::factory()->create([
            'role_id'   => $role->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $matiere = Matiere::create([
            'tenant_id' => $this->tenant->id,
            'nom_fr'    => 'Mathématiques',
        ]);

        $this->groupe = Groupe::create([
            'tenant_id'      => $this->tenant->id,
            'matiere_id'     => $matiere->id,
            'nom'            => '3e A',
            'niveau_scolaire' => '3e',
        ]);
    }

    private function makeEvaluation(array $attrs = []): Evaluation
    {
        return Evaluation::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'groupe_id'       => $this->groupe->id,
            'titre'           => 'Test intégration',
            'type_eval'       => 'devoir',
            'date_evaluation' => '2026-07-10',
            'note_sur'        => 20,
            'coefficient'     => 1,
            'trimestre'       => 1,
        ], $attrs));
    }

    // ── WhatsApp ────────────────────────────────────────

    public function test_envoyer_message_whatsapp(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/whatsapp/send', [
                'to'      => '213555123456',
                'message' => 'Test depuis EduGest',
            ])
            ->assertStatus(422);
    }

    public function test_lister_messages_whatsapp(): void
    {
        WhatsappMessage::create([
            'tenant_id'  => $this->tenant->id,
            'to_number'  => '213555123456',
            'direction'  => 'out',
            'type'       => 'text',
            'content'    => 'Test',
            'status'     => 'sent',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/whatsapp/messages')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_webhook_verification(): void
    {
        $this->getJson('/api/v1/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=edugest_verify&hub_challenge=12345')
            ->assertStatus(200);
    }

    // ── Google Classroom ─────────────────────────────────

    public function test_status_non_connecte(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/google/classroom/status')
            ->assertStatus(200)
            ->assertJsonPath('data.connected', false);
    }

    public function test_connecte_status(): void
    {
        GoogleClassroomConnexion::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->admin->id,
            'email'     => 'test@example.com',
            'token'     => Crypt::encryptString(json_encode(['access_token' => 'test'])),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/google/classroom/status')
            ->assertStatus(200)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.email', 'test@example.com');
    }

    public function test_lier_evaluation(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/google/classroom/links', [
                'evaluation_id' => $evaluation->id,
                'gc_course_id'  => 'course-123',
                'gc_course_name' => 'Maths 3e',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.gc_course_id', 'course-123');
    }

    public function test_lister_liaisons(): void
    {
        $evaluation = $this->makeEvaluation(['titre' => 'Test']);

        GoogleCourseLiaison::create([
            'tenant_id'     => $this->tenant->id,
            'evaluation_id' => $evaluation->id,
            'gc_course_id'  => 'course-456',
            'gc_course_name'=> 'Physique',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/google/classroom/links')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_supprimer_liaison(): void
    {
        $evaluation = $this->makeEvaluation(['titre' => 'Test delete']);

        $liaison = GoogleCourseLiaison::create([
            'tenant_id'     => $this->tenant->id,
            'evaluation_id' => $evaluation->id,
            'gc_course_id'  => 'course-789',
        ]);

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/v1/google/classroom/links/{$liaison->id}")
            ->assertStatus(200);
    }
}
