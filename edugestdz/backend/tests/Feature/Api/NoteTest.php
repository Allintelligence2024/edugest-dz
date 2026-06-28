<?php

namespace Tests\Feature\Api;

use App\Models\{Note, Evaluation, Eleve, Groupe, Matiere, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_evaluation_et_saisir_notes(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe  = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $eval = $this->withToken($this->token)
            ->postJson('/api/v1/evaluations', [
                'groupe_id'       => $groupe->id,
                'matiere_id'      => $matiere->id,
                'type_eval'       => 'devoir',
                'note_sur'        => 20,
                'trimestre'       => 2,
                'date_evaluation' => now()->toDateString(),
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/evaluations/{$eval['id']}/notes", [
                'notes' => [
                    ['eleve_id' => $eleve->id, 'note' => 15.5, 'appreciation' => 'Très bien'],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'note' => 15.5]);
    }

    public function test_modifier_note(): void
    {
        $note = Note::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/notes/{$note->id}", [
                'note' => 18,
                'appreciation' => 'Excellent',
            ])
            ->assertStatus(200);

        $this->assertEquals(18, $note->fresh()->note);
    }
}
