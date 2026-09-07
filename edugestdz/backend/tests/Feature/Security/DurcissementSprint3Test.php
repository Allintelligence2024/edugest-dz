<?php

namespace Tests\Feature\Security;

use App\Console\Commands\RlsStatusCommand;
use App\Models\Eleve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprint 3 — Durcissement transverse : tenant fail-closed, RLS, secrets,
 * réponses ne divulguant pas d'information.
 */
class DurcissementSprint3Test extends TestCase
{
    use RefreshDatabase;

    // ══════════════════════════════════════════════════
    // TENANT FAIL-CLOSED
    // ══════════════════════════════════════════════════

    /**
     * Sans contexte tenant, une requête ne doit RIEN retourner.
     * Le piège serait qu'un tenant null soit interprété comme « pas de
     * filtre », donnant accès à tous les établissements.
     */
    public function test_sans_tenant_resolu_aucune_donnee_nest_visible(): void
    {
        $tenant = \App\Models\Tenant::factory()->create(['statut' => 'actif']);

        config(['tenant.current_id' => $tenant->id]);
        Eleve::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        config(['tenant.current_id' => null]);

        $this->assertSame(0, Eleve::count(), 'Sans tenant, le scope doit filtrer 1=0');
    }

    public function test_sans_tenant_resolu_la_creation_echoue(): void
    {
        config(['tenant.current_id' => null]);

        $this->expectException(\RuntimeException::class);

        Eleve::create([
            'nom'    => 'TEST',
            'prenom' => 'Sans tenant',
            'statut' => 'actif',
        ]);
    }

    /** Le middleware doit répondre 403, pas 404 (pas de divulgation). */
    public function test_labsence_de_tenant_renvoie_403_et_non_404(): void
    {
        $reponse = $this->getJson('/api/v1/eleves');

        // 401 si non authentifié, 403 si authentifié sans tenant —
        // mais jamais 404, qui renseignerait sur l'existence des ressources.
        $this->assertNotSame(404, $reponse->status());
        $this->assertContains($reponse->status(), [401, 403]);
    }

    // ══════════════════════════════════════════════════
    // RLS POSTGRESQL
    // ══════════════════════════════════════════════════

    public function test_les_tables_attendues_sont_protegees_par_rls(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS spécifique à PostgreSQL');
        }

        $etat = app(RlsStatusCommand::class)->collecter();

        $this->assertSame(
            [],
            $etat['non_protegees'],
            sprintf(
                "%d table(s) sans RLS effectif :\n  - %s",
                count($etat['non_protegees']),
                implode("\n  - ", $etat['non_protegees'])
            )
        );
    }

    public function test_la_commande_rls_status_sexecute(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS spécifique à PostgreSQL');
        }

        $this->artisan('rls:status')->assertExitCode(0);
    }

    // ══════════════════════════════════════════════════
    // SECRETS
    // ══════════════════════════════════════════════════

    /** Les clés de signature doivent être distinctes les unes des autres. */
    public function test_les_cles_de_signature_sont_distinctes(): void
    {
        $appKey   = config('app.key');
        $qrKey    = config('security.qr.signing_key');
        $auditKey = config('security.audit.keys')[config('security.audit.key_version')] ?? null;

        $this->assertNotEmpty($qrKey);
        $this->assertNotEmpty($auditKey);

        // En test, les clés sont fournies par phpunit.xml : elles doivent
        // déjà être séparées d'APP_KEY, sinon le principe est perdu.
        $this->assertNotSame($appKey, $qrKey, 'QR_SIGNING_KEY doit être distincte de APP_KEY');
        $this->assertNotSame($appKey, $auditKey, 'AUDIT_CHAIN_KEY doit être distincte de APP_KEY');
    }

    /** Les cron ne doivent pas tourner sans secret configuré (fail-closed). */
    public function test_les_routes_cron_refusent_sans_secret(): void
    {
        config(['services.cron.secret' => null]);

        $this->getJson('/api/v1/cron/prune')->assertStatus(503);
    }

    public function test_les_routes_cron_refusent_un_mauvais_secret(): void
    {
        config(['services.cron.secret' => 'le-vrai-secret']);

        $this->getJson('/api/v1/cron/prune', ['Authorization' => 'Bearer mauvais'])
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════
    // CORS
    // ══════════════════════════════════════════════════

    /** Les cookies httpOnly exigent supports_credentials. */
    public function test_le_cors_autorise_les_credentials(): void
    {
        $this->assertTrue(
            config('cors.supports_credentials'),
            'Sans credentials, le navigateur ne transmettrait pas le cookie de refresh'
        );
    }

    /** Avec credentials, le joker '*' est interdit par les navigateurs. */
    public function test_le_cors_nutilise_pas_de_joker_dorigine(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins', []));
    }
}
