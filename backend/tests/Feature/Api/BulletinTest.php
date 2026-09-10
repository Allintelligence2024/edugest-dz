<?php

namespace Tests\Feature\Api;

use App\Models\{Bulletin, Eleve, Groupe, User, Tenant, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class BulletinTest extends TestCase
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
        $this->token = JWTAuth::fromUser($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_liste_bulletins(): void
    {
        Bulletin::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $this->withToken($this->token)->getJson('/api/v1/bulletins')->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_generer_bulletins(): void
    {
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/bulletins/generer', [
                'groupe_id'      => $groupe->id,
                'trimestre'      => 'T1',
                'annee_scolaire' => '2025-2026',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_pdf_bulletin_dispatch_job_when_not_generated(): void
    {
        $bulletin = Bulletin::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'statut_pdf'  => 'en_attente',
            'fichier_url' => null,
        ]);
        $this->withToken($this->token)
            ->getJson("/api/v1/bulletins/{$bulletin->id}/pdf")
            ->assertStatus(202);
    }

    public function test_pdf_bulletin_return_file_when_ready(): void
    {
        $storagePath = storage_path('app/public');
        @mkdir($storagePath, 0755, true);
        $relativePath = 'bulletins/test_bulletin.pdf';
        file_put_contents($storagePath . '/' . $relativePath, '%PDF-1.4 fake');

        $bulletin = Bulletin::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'statut_pdf'  => 'termine',
            'fichier_url' => $relativePath,
        ]);
        $this->withToken($this->token)
            ->getJson("/api/v1/bulletins/{$bulletin->id}/pdf")
            ->assertStatus(200);

        @unlink($storagePath . '/' . $relativePath);
    }

    public function test_envoyer_bulletin(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $bulletin = Bulletin::factory()->create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id]);
        $this->withToken($this->token)->postJson("/api/v1/bulletins/{$bulletin->id}/envoyer")->assertStatus(200);
    }
}
