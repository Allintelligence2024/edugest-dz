<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\Tenant;
use App\Services\EleveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG#30 — Sécurité et correctness des jetons QR de présence.
 *
 * Ces tests garantissent que la régression précédente (payload signé avec
 * now() puis vérifié avec updated_at ⇒ jeton jamais valide, scan O(N))
 * ne peut pas revenir.
 */
class QrTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    private EleveService $service;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EleveService::class);

        $this->tenantA = Tenant::factory()->create(['statut' => 'actif']);
        $this->tenantB = Tenant::factory()->create(['statut' => 'actif']);

        config(['tenant.current_id' => $this->tenantA->id]);
    }

    private function eleve(Tenant $tenant): Eleve
    {
        return Eleve::factory()->create(['tenant_id' => $tenant->id]);
    }

    /** Un jeton fraîchement généré doit être vérifiable — le cas nominal qui était cassé. */
    public function test_un_jeton_genere_est_verifiable(): void
    {
        $eleve = $this->eleve($this->tenantA);

        $token   = $this->service->genererTokenQR($eleve);
        $payload = $this->service->verifierTokenQR($token);

        $this->assertNotNull($payload, 'Le jeton QR généré doit être vérifiable');
        $this->assertSame($eleve->id, $payload['eleve']);
        $this->assertSame($this->tenantA->id, $payload['tenant']);
    }

    /** La génération est déterministe : pas de dérive temporelle entre signature et vérification. */
    public function test_la_generation_est_deterministe(): void
    {
        $eleve = $this->eleve($this->tenantA);

        $premier = $this->service->genererTokenQR($eleve);
        $second  = $this->service->genererTokenQR($eleve->refresh());

        $this->assertSame($premier, $second);
        $this->assertNotNull($this->service->verifierTokenQR($second));
    }

    /** Le hash est persisté et indexable — la vérification ne doit plus scanner tous les élèves. */
    public function test_le_hash_du_jeton_est_persiste(): void
    {
        $eleve = $this->eleve($this->tenantA);
        $token = $this->service->genererTokenQR($eleve);

        $eleve->refresh();

        $this->assertNotNull($eleve->qr_token_hash);
        $this->assertSame(hash('sha256', $token), $eleve->qr_token_hash);
        $this->assertNotNull($eleve->qr_generated_at);
    }

    /** Le jeton en clair ne doit jamais fuiter dans une réponse API. */
    public function test_le_hash_nest_pas_serialise(): void
    {
        $eleve = $this->eleve($this->tenantA);
        $this->service->genererTokenQR($eleve);

        $this->assertArrayNotHasKey('qr_token_hash', $eleve->refresh()->toArray());
    }

    /** Signature falsifiée ⇒ rejet. */
    public function test_un_jeton_forge_est_rejete(): void
    {
        $eleve = $this->eleve($this->tenantA);
        $token = $this->service->genererTokenQR($eleve);

        [$prefix, $body] = explode('.', $token);
        $forge = $prefix . '.' . $body . '.' . rtrim(strtr(base64_encode('signature-bidon'), '+/', '-_'), '=');

        $this->assertNull($this->service->verifierTokenQR($forge));
    }

    /** Payload modifié (changement d'élève) sans re-signature ⇒ rejet. */
    public function test_un_payload_altere_est_rejete(): void
    {
        $eleve  = $this->eleve($this->tenantA);
        $victime = $this->eleve($this->tenantA);

        $token = $this->service->genererTokenQR($eleve);
        [$prefix, , $signature] = explode('.', $token);

        $nouveauBody = rtrim(strtr(base64_encode(json_encode([
            'v' => 1,
            'e' => (string) $victime->id,
            't' => (string) $this->tenantA->id,
        ])), '+/', '-_'), '=');

        $this->assertNull($this->service->verifierTokenQR("{$prefix}.{$nouveauBody}.{$signature}"));
    }

    /** Un badge valide d'un autre établissement ne doit pas ouvrir de présence ici. */
    public function test_un_jeton_dun_autre_tenant_est_rejete(): void
    {
        config(['tenant.current_id' => $this->tenantB->id]);
        $eleveB = $this->eleve($this->tenantB);
        $tokenB = $this->service->genererTokenQR($eleveB);

        // On repasse dans le contexte du tenant A : le jeton de B doit échouer.
        config(['tenant.current_id' => $this->tenantA->id]);

        $this->assertNull($this->service->verifierTokenQR($tokenB));
    }

    /** Révocation : l'ancien badge (perdu/volé) devient inutilisable. */
    public function test_la_revocation_invalide_lancien_jeton(): void
    {
        $eleve = $this->eleve($this->tenantA);
        $ancien = $this->service->genererTokenQR($eleve);

        $nouveau = $this->service->revoquerTokenQR($eleve->refresh());

        $this->assertNotSame($ancien, $nouveau);
        $this->assertNull($this->service->verifierTokenQR($ancien), 'Le jeton révoqué doit être refusé');
        $this->assertNotNull($this->service->verifierTokenQR($nouveau));
    }

    /** Un badge au-delà du TTL est refusé. */
    public function test_un_jeton_expire_est_rejete(): void
    {
        config(['security.qr.ttl_days' => 30]);

        $eleve = $this->eleve($this->tenantA);
        $token = $this->service->genererTokenQR($eleve);

        $eleve->forceFill(['qr_generated_at' => now()->subDays(31)])->save();

        $this->assertNull($this->service->verifierTokenQR($token));
    }

    /** Entrées malformées : aucune exception, juste un refus. */
    public function test_les_jetons_malformes_sont_rejetes(): void
    {
        foreach (['', 'nimportequoi', 'a.b', 'a.b.c.d', '$2y$10$abcdefghijklmnop', 'EGQR1..'] as $mauvais) {
            $this->assertNull(
                $this->service->verifierTokenQR($mauvais),
                "Le jeton malformé « {$mauvais} » doit être rejeté"
            );
        }
    }
}
