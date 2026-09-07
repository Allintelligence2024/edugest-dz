<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Sprint 3 — Refresh tokens : cookie httpOnly, rotation, détection de vol.
 *
 * Avant : le jeton d'accès était rangé dans localStorage (lisible par tout
 * script injecté) et le frontend envoyait un `refresh_token` que le backend
 * n'émettait jamais — le rafraîchissement était donc purement décoratif.
 */
class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $motDePasse = 'MotDePasse#2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role = Role::factory()->create(['nom' => 'admin']);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
            'password'  => Hash::make($this->motDePasse),
        ]);

        config(['tenant.current_id' => $this->tenant->id]);

        // throttle:auth limite à 10 requêtes / 15 min par IP. Ces tests
        // enchaînent des dizaines d'appels depuis la même IP fictive : sans
        // remise à zéro, les derniers recevraient un 429 sans rapport avec
        // ce qu'on cherche à vérifier.
        RateLimiter::clear('auth');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function seConnecter()
    {
        return $this->postJson('/api/v1/auth/login', [
            'email'    => $this->user->email,
            'password' => $this->motDePasse,
        ]);
    }

    // ══════════════════════════════════════════════════
    // COOKIE
    // ══════════════════════════════════════════════════

    public function test_le_login_pose_un_cookie_refresh_httponly(): void
    {
        $reponse = $this->seConnecter();

        $reponse->assertOk();
        $reponse->assertCookie(RefreshTokenService::COOKIE);

        $cookie = collect($reponse->headers->getCookies())
            ->firstWhere('getName', RefreshTokenService::COOKIE)
            ?? collect($reponse->headers->getCookies())
                ->first(fn ($c) => $c->getName() === RefreshTokenService::COOKIE);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly(), 'Le cookie de refresh doit être httpOnly (anti-XSS)');
    }

    public function test_le_refresh_token_est_stocke_hashe(): void
    {
        $this->seConnecter()->assertOk();

        $ligne = DB::table('refresh_tokens')->where('user_id', $this->user->id)->first();

        $this->assertNotNull($ligne);
        $this->assertSame(64, strlen($ligne->token_hash), 'Un SHA-256 hexadécimal fait 64 caractères');
        $this->assertNull($ligne->revoked_at);
    }

    // ══════════════════════════════════════════════════
    // ROTATION
    // ══════════════════════════════════════════════════

    public function test_le_refresh_fait_tourner_le_jeton(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        $reponse = $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh');

        $reponse->assertOk();
        $reponse->assertJsonStructure(['success', 'access_token', 'expires_in']);

        // L'ancien jeton doit être consommé.
        $ancien = DB::table('refresh_tokens')->where('token_hash', hash('sha256', $clair))->first();
        $this->assertNotNull($ancien->revoked_at);
        $this->assertSame('rotated', $ancien->revoked_reason);

        // Un nouveau jeton actif doit exister.
        $this->assertSame(
            1,
            DB::table('refresh_tokens')->where('user_id', $this->user->id)->whereNull('revoked_at')->count()
        );
    }

    /**
     * Cœur du modèle de sécurité : rejouer un jeton déjà consommé signale un
     * vol. Toute la lignée doit être révoquée, y compris le jeton légitime
     * détenu par la vraie victime.
     */
    public function test_la_reutilisation_dun_jeton_revoque_toute_la_lignee(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        // Usage légitime.
        $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        // L'attaquant rejoue le jeton volé.
        $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);

        $actifs = DB::table('refresh_tokens')
            ->where('user_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(0, $actifs, 'Toute la lignée doit être révoquée après détection de réutilisation');
    }

    public function test_un_jeton_inconnu_est_rejete(): void
    {
        $this->withCookie(RefreshTokenService::COOKIE, str_repeat('z', 64))
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    public function test_un_jeton_expire_est_rejete(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        DB::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $clair))
            ->update(['expires_at' => now()->subDay()]);

        $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    public function test_un_utilisateur_desactive_ne_peut_plus_rafraichir(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        $this->user->update(['statut' => 'suspendu']);

        $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    /** Le rafraîchissement ne doit pas exiger un JWT valide. */
    public function test_le_refresh_est_accessible_sans_jwt(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        // Diagnostic : distinguer « le cookie n'arrive pas » de « la rotation
        // refuse ». Un 401 seul ne permet pas de trancher.
        $ligne = \Illuminate\Support\Facades\DB::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $clair))->first();

        $this->assertNotNull($ligne, 'Le jeton devrait être en base');
        $this->assertNull($ligne->revoked_at, 'Le jeton ne devrait pas être révoqué');

        // La rotation appelée directement doit réussir : si elle échoue ici,
        // le problème est dans le service ; si elle réussit mais que la
        // requête HTTP renvoie 401, le problème est le transport du cookie.
        $rotation = $service->faireTourner($clair, request());
        $this->assertNotNull($rotation, 'faireTourner() a refusé un jeton valide');

        // Nouveau jeton pour l'appel HTTP (le précédent vient d'être consommé).
        [$clair2] = $service->emettre($this->user, request());

        // Sonde : capturer ce que le serveur reçoit réellement. Le 401 est
        // TOKEN_EXPIRED, ce qui signifie que le contrôleur est tombé dans le
        // repli JWT — donc que $presente était vide côté serveur alors que le
        // test envoie bien un cookie.
        $vu = [];
        \Illuminate\Support\Facades\Route::post('/api/v1/_sonde_cookie', function (\Illuminate\Http\Request $r) use (&$vu) {
            $vu = [
                'cookie_helper' => $r->cookie(RefreshTokenService::COOKIE),
                'cookie_bag'    => $r->cookies->get(RefreshTokenService::COOKIE),
                'tous'          => array_keys($r->cookies->all()),
                'header'        => $r->header('Cookie'),
            ];
            return response()->json(['ok' => true]);
        });

        $this->withCookie(RefreshTokenService::COOKIE, $clair2)
            ->postJson('/api/v1/_sonde_cookie');

        $reponse = $this->withCookie(RefreshTokenService::COOKIE, $clair2)
            ->postJson('/api/v1/auth/refresh');

        $this->assertSame(
            200,
            $reponse->status(),
            "Réponse: {$reponse->getContent()}\nCe que le serveur voit: " . json_encode($vu)
        );
    }

    // ══════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════

    public function test_le_logout_revoque_les_refresh_tokens(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        $this->actingAs($this->user, 'api')
            ->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(
            0,
            DB::table('refresh_tokens')->where('user_id', $this->user->id)->whereNull('revoked_at')->count()
        );

        // Le jeton révoqué ne doit plus rien ouvrir.
        $this->withCookie(RefreshTokenService::COOKIE, $clair)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════
    // SERVICE
    // ══════════════════════════════════════════════════

    public function test_la_purge_supprime_les_jetons_anciens(): void
    {
        $service = app(RefreshTokenService::class);
        [$clair] = $service->emettre($this->user, request());

        DB::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $clair))
            ->update(['expires_at' => now()->subDays(40)]);

        $this->assertSame(1, $service->purger());
        $this->assertSame(0, DB::table('refresh_tokens')->count());
    }

    public function test_revoquer_utilisateur_ferme_toutes_les_sessions(): void
    {
        $service = app(RefreshTokenService::class);
        $service->emettre($this->user, request());
        $service->emettre($this->user, request());

        $this->assertSame(2, $service->revoquerUtilisateur($this->user->id, 'incident'));
        $this->assertSame(
            0,
            DB::table('refresh_tokens')->where('user_id', $this->user->id)->whereNull('revoked_at')->count()
        );
    }
}
