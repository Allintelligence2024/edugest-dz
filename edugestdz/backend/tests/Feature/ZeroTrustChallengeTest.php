<?php

namespace Tests\Feature;

use App\Http\Middleware\ZeroTrustMiddleware;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\RiskScoreEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sprint 6 (pentest C3) — boucler le flux Zero-Trust.
 *
 * Avant : le code du challenge était renvoyé dans la réponse 428 (le client
 * recevait le secret à présenter) et verifierChallenge() n'avait aucune
 * route consommatrice. Maintenant : code hors-bande (e-mail) + endpoint de
 * vérification qui déclare l'appareil courant comme confiance.
 */
class ZeroTrustChallengeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $role      = Role::firstOrCreate(['nom' => 'enseignant']);
        $this->user  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->fromUser($this->user);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_code_valide_enregistre_lappareil_courant(): void
    {
        $code = app(DeviceFingerprintService::class)->creerChallenge($this->user)['challenge'];

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/security/zero-trust/verify', ['code' => $code])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trusted_devices', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_code_invalide_rejete_sans_enregistrer_appareil(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/security/zero-trust/verify', ['code' => 'code-errone'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('trusted_devices', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_code_absent_rejete_en_validation(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/security/zero-trust/verify', [])
            ->assertStatus(422);
    }

    public function test_route_inaccessible_sans_authentification(): void
    {
        $this->postJson('/api/v1/security/zero-trust/verify', ['code' => 'peu-importe'])
            ->assertStatus(401);
    }

    public function test_le_middleware_ne_divulgue_plus_le_code(): void
    {
        $this->actingAs($this->user, 'api');

        $middleware = new ZeroTrustMiddleware(
            app(RiskScoreEngine::class),
            app(DeviceFingerprintService::class)
        );

        // Requête synthétique sans en-têtes : appareil inconnu (+40) et pas
        // de Sec-Ch-Ua (+15) → score 55 > seuil strict 50 → branche 428.
        $reponse = $middleware->handle(
            Request::create('/api/v1/test-strict', 'GET'),
            fn () => response()->json(['success' => true]),
            'strict'
        );

        $contenu = (string) $reponse->getContent();

        $this->assertSame(428, $reponse->getStatusCode());
        // Le 428 référence le challenge par son id, jamais par le code…
        $this->assertStringContainsString('challenge_id', $contenu);
        // …et la clé « challenge » (code brut) a disparu du corps (C3).
        $this->assertStringNotContainsString('"challenge"', $contenu);
    }
}
