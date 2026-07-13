<?php
namespace Tests\Feature\Api;

use App\Models\{Eleve, Seance, Presence, Tenant, User, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Mail};
use Tests\TestCase;

class QrCodePresenceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $enseignant;
    protected Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $role = Role::firstOrCreate(
            ['nom' => 'enseignant', 'tenant_id' => $this->tenant->id],
            ['nom' => 'enseignant', 'label_fr' => 'Enseignant', 'is_system' => true]
        );

        $this->enseignant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->seance = Seance::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_demarrer_session_qr(): void
    {
        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_fermer_session_qr(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/fermer', [
                'seance_id' => $this->seance->id,
            ]);

        $res->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_scanner_token_valide(): void
    {
        $demarrage = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $token = $demarrage->json('data.token');
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_scanner_token_invalide(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => 'token_invalide_123',
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRE'],
            ]);
    }

    public function test_session_statut(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res = $this->actingAs($this->enseignant, 'api')
            ->getJson("/api/v1/qr-code/session/{$this->seance->id}/statut");

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => ['active' => true],
            ]);
    }

    public function test_scanner_deux_fois_echoue(): void
    {
        $demarrage = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $token = $demarrage->json('data.token');
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        // Premier scan
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        // Deuxième scan — doublon
        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        $res->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error'   => ['code' => 'DEJA_POINTE'],
            ]);
    }
}
