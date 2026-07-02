<?php
namespace Tests\Feature;

use App\Models\{Eleve, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_login_email_invalide_echoue(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'pas-un-email',
            'password' => 'secret',
        ])->assertStatus(422);
    }

    public function test_login_mot_de_passe_manquant_echoue(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
        ])->assertStatus(422);
    }

    public function test_login_email_invalide_retourne_erreur(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => 'secret',
        ])->assertStatus(422);
    }

    public function test_eleve_niveau_invalide_echoue(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/eleves', [
                'nom' => 'Test',
                'prenom' => 'Test',
                'date_naissance' => '2010-01-01',
                'sexe' => 'M',
                'niveau_scolaire' => 'LICENCE',
            ])
            ->assertStatus(422);
    }

    public function test_eleve_sexe_invalide_echoue(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/eleves', [
                'nom' => 'Test',
                'prenom' => 'Test',
                'date_naissance' => '2010-01-01',
                'sexe' => 'X',
                'niveau_scolaire' => '3AS',
            ])
            ->assertStatus(422);
    }

    public function test_eleve_date_naissance_future_echoue(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/eleves', [
                'nom' => 'Test',
                'prenom' => 'Test',
                'date_naissance' => now()->addYear()->format('Y-m-d'),
                'sexe' => 'M',
                'niveau_scolaire' => '3AS',
            ])
            ->assertStatus(422);
    }

    public function test_eleve_introuvable_retourne_404(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/eleves/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_pagination_per_page_max(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/eleves?per_page=200')
            ->assertStatus(200);
    }

    public function test_facture_introuvable_retourne_404(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/finance/factures/uuid-inexistant-00000000')
            ->assertStatus(404);
    }

    public function test_supprimer_eleve_existant(): void
    {
        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $parent = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleParent->id]);
        $tokenParent = auth('api')->login($parent);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($tokenParent)
            ->deleteJson("/api/v1/eleves/{$eleve->id}")
            ->assertStatus(200);
    }

    public function test_budget_dashboard_accessible(): void
    {
        $roleEns = Role::factory()->create(['nom' => 'enseignant']);
        $enseignant = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleEns->id]);
        $tokenEns = auth('api')->login($enseignant);

        $this->withToken($tokenEns)
            ->getJson('/api/v1/budget/dashboard')
            ->assertStatus(200);
    }
}
