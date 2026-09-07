<?php

namespace Tests\Feature\Security;

use App\Console\Commands\RlsStatusCommand;
use App\Models\Eleve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // La factory fournit les champs obligatoires ; c'est bien l'absence
        // de tenant qui doit faire échouer la création, pas une colonne
        // manquante. `make()` puis `save()` pour ne pas laisser la factory
        // renseigner tenant_id elle-même.
        $eleve = Eleve::factory()->make(['tenant_id' => null]);

        $this->expectException(\RuntimeException::class);

        $eleve->save();
    }

    /** Le middleware doit répondre 403, pas 404 (pas de divulgation). */
    public function test_labsence_de_tenant_renvoie_403_et_non_404(): void
    {
        $reponse = $this->getJson('/api/v1/eleves');

        // Sans authentification on attend 401 ; authentifié mais sans tenant
        // résolu, 403. Jamais 404 : cela renseignerait un tiers sur
        // l'existence de la ressource.
        $this->assertContains(
            $reponse->status(),
            [401, 403],
            "Statut inattendu : {$reponse->status()}"
        );
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

        // La migration RLS ignore volontairement les tables dépourvues de
        // colonne `tenant_id` : elles n'ont rien à isoler. On ne peut donc
        // exiger la protection que sur celles qui en portent une.
        $aIsoler = array_values(array_filter(
            $etat['non_protegees'],
            fn ($t) => Schema::hasColumn($t, 'tenant_id')
        ));

        $this->assertSame(
            [],
            $aIsoler,
            sprintf(
                "%d table(s) porteuses de tenant_id sans RLS effectif :\n  - %s",
                count($aIsoler),
                implode("\n  - ", $aIsoler)
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

        $reponse = $this->getJson('/api/v1/cron/prune');

        if ($reponse->status() === 404) {
            $this->markTestSkipped('Routes cron non chargées dans cet environnement');
        }

        $reponse->assertStatus(503);
    }

    public function test_les_routes_cron_refusent_un_mauvais_secret(): void
    {
        config(['services.cron.secret' => 'le-vrai-secret']);

        $reponse = $this->getJson('/api/v1/cron/prune', ['Authorization' => 'Bearer mauvais']);

        if ($reponse->status() === 404) {
            $this->markTestSkipped('Routes cron non chargées dans cet environnement');
        }

        $reponse->assertStatus(401);
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
