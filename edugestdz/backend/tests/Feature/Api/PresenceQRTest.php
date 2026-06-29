<?php

namespace Tests\Feature\Api;

use App\Models\{Eleve, Seance, Cours, Groupe, User, Tenant, Role};
use App\Services\EleveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PresenceQRTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;
    protected Eleve $eleve;
    protected Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);

        $this->eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id, 'qr_code' => 'dummy']);
        $matiere = \App\Models\Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe  = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $cours   = Cours::factory()->create(['tenant_id' => $this->tenant->id, 'groupe_id' => $groupe->id, 'matiere_id' => $matiere->id, 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'actif']);
        $this->seance = Seance::factory()->create(['tenant_id' => $this->tenant->id, 'cours_id' => $cours->id, 'statut' => 'en_cours']);
    }

    public function test_generer_qrcode(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/eleves/{$this->eleve->id}/qrcode")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_scan_presence_valide(): void
    {
        $service = app(EleveService::class);
        $token = $service->genererTokenQR($this->eleve);

        $this->withToken($this->token)
            ->postJson('/api/v1/presences/scan', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_scan_qr_invalide(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/presences/scan', [
                'qr_token'  => 'token_invalide',
                'seance_id' => $this->seance->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'QR_INVALIDE');
    }

    public function test_double_scan_refuse(): void
    {
        $service = app(EleveService::class);
        $token = $service->genererTokenQR($this->eleve);

        $this->withToken($this->token)
            ->postJson('/api/v1/presences/scan', ['qr_token' => $token, 'seance_id' => $this->seance->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/presences/scan', ['qr_token' => $token, 'seance_id' => $this->seance->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_POINTE');
    }

    public function test_scan_eleve_autre_tenant(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        $service = app(EleveService::class);
        $token = $service->genererTokenQR($autreEleve);

        $this->withToken($this->token)
            ->postJson('/api/v1/presences/scan', ['qr_token' => $token, 'seance_id' => $this->seance->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'QR_INVALIDE');
    }
}
