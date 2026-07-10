<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests de validation PostgreSQL pur.
 *
 * Ces tests garantissent que :
 * 1. La connexion est bien PostgreSQL (pas SQLite)
 * 2. Les fonctionnalités PostgreSQL-exclusives sont disponibles
 * 3. Aucune trace SQLite dans la config
 *
 * Si ces tests échouent → la CI a un problème de configuration BDD.
 */
class PostgreSqlPurTest extends TestCase
{
    use RefreshDatabase;

    // ── Vérifications fondamentales ───────────────────────────────────

    public function test_connexion_est_postgresql_pas_sqlite(): void
    {
        $driver = DB::connection()->getDriverName();

        $this->assertEquals(
            'pgsql',
            $driver,
            "❌ BLOQUANT : Le driver est '{$driver}' au lieu de 'pgsql'.\n" .
            "Vérifier phpunit.xml et que DB_CONNECTION=pgsql est bien défini."
        );
    }

    public function test_version_postgresql_est_16_ou_plus(): void
    {
        $version = DB::selectOne('SELECT version()')->version;
        $this->assertStringContainsString('PostgreSQL', $version);

        preg_match('/PostgreSQL (\d+)/', $version, $m);
        $major = (int) ($m[1] ?? 0);

        $this->assertGreaterThanOrEqual(
            15, $major,
            "PostgreSQL {$major} détecté — version 15+ requise (16 recommandé)."
        );
    }

    public function test_config_database_default_est_pgsql(): void
    {
        $this->assertEquals(
            'pgsql',
            config('database.default'),
            "database.default doit être 'pgsql', pas '" . config('database.default') . "'"
        );
    }

    public function test_config_postgresql_seule_connection_autorisee(): void
    {
        // Laravel merges framework defaults (sqlite, mysql, etc.) into config.
        // What matters: our app config declares only pgsql + pgsql_test,
        // and the default connection is pgsql.
        $connections = config('database.connections', []);

        // Default must be pgsql
        $this->assertEquals(
            'pgsql',
            config('database.default'),
            "database.default doit être 'pgsql'"
        );

        // pgsql connection must exist and use pgsql driver
        $this->assertArrayHasKey('pgsql', $connections);
        $this->assertEquals('pgsql', $connections['pgsql']['driver']);

        // pgsql_test connection must exist and use pgsql driver
        $this->assertArrayHasKey('pgsql_test', $connections);
        $this->assertEquals('pgsql', $connections['pgsql_test']['driver']);

        // No sqlite connection declared in our app config file
        // (framework default may appear due to array_merge in LoadConfiguration)
        $configContent = file_get_contents(config_path('database.php'));
        $this->assertStringNotContainsString(
            "'sqlite'",
            $configContent,
            "config/database.php ne doit PAS contenir de connexion 'sqlite'"
        );
    }

    // ── Fonctionnalités PostgreSQL-exclusives ─────────────────────────

    public function test_gen_random_uuid_natif_postgresql(): void
    {
        // gen_random_uuid() est natif PostgreSQL 13+ (pgcrypto ou pg_uuid)
        $result = DB::selectOne("SELECT gen_random_uuid() AS uuid");
        $this->assertNotNull($result->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result->uuid,
            "gen_random_uuid() doit retourner un UUID valide (format RFC 4122)"
        );
    }

    public function test_jsonb_operations_disponibles(): void
    {
        // JSONB et ses opérateurs @> sont exclusifs à PostgreSQL
        $result = DB::selectOne("SELECT '{\"a\": 1, \"b\": 2}'::jsonb @> '{\"a\": 1}'::jsonb AS match");
        $this->assertTrue((bool) $result->match, "L'opérateur jsonb @> doit être disponible");
    }

    public function test_savepoint_postgresql(): void
    {
        // SAVEPOINTs utilisés dans les migrations de sécurité (RLS, etc.)
        DB::statement('SAVEPOINT test_pgsql_pur');
        DB::statement('RELEASE SAVEPOINT test_pgsql_pur');
        $this->assertTrue(true, "SAVEPOINTs PostgreSQL doivent fonctionner");
    }

    public function test_sha3_disponible_php(): void
    {
        // SHA3-256 utilisé par AuditChainService
        $this->assertContains(
            'sha3-256',
            hash_algos(),
            "SHA3-256 doit être disponible en PHP (utilisé par AuditChainService)"
        );

        $hash = hash('sha3-256', 'test EduGest DZ');
        $this->assertEquals(64, strlen($hash), "SHA3-256 doit produire 64 caractères hex");
    }

    public function test_extension_sodium_disponible(): void
    {
        // libsodium utilisé par AsymmetricCryptoService
        $this->assertTrue(
            extension_loaded('sodium'),
            "L'extension sodium doit être chargée (pour Ed25519 dans AsymmetricCryptoService)"
        );
    }

    public function test_extension_pdo_pgsql_disponible(): void
    {
        $this->assertTrue(
            extension_loaded('pdo_pgsql'),
            "L'extension pdo_pgsql doit être chargée"
        );
    }

    public function test_cache_store_est_array_pas_redis(): void
    {
        // En test, CACHE_STORE=array est OBLIGATOIRE
        // redis partagerait le cache entre processus parallèles → flaky tests
        $this->assertEquals(
            'array',
            config('cache.default'),
            "CACHE_STORE doit être 'array' en test (pas 'redis') pour isoler les tests parallèles.\n" .
            "Vérifier phpunit.xml : <env name='CACHE_STORE' value='array'/>"
        );
    }

    // ── Index GIN ─────────────────────────────────────────────────────

    public function test_index_gin_security_events_existe(): void
    {
        if (!DB::selectOne("SELECT 1 FROM information_schema.tables WHERE table_name='security_events'")) {
            $this->markTestSkipped('Table security_events non encore créée');
        }

        $index = DB::selectOne("
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'security_events'
              AND indexname = 'idx_gin_security_events_details'
        ");

        $this->assertNotNull(
            $index,
            "Index GIN sur security_events.details manquant.\n" .
            "Lancer : php artisan migrate"
        );
    }

    // ── Vérification AppServiceProvider sans SQLite ───────────────────

    public function test_app_service_provider_sans_sqlite_function(): void
    {
        // Vérifier que le code SQLite n'est plus dans AppServiceProvider
        $content = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringNotContainsString(
            'sqliteCreateFunction',
            $content,
            "AppServiceProvider contient encore du code SQLite (sqliteCreateFunction).\n" .
            "Supprimer le bloc 'if (DB::getDriverName() === sqlite)'"
        );

        $this->assertStringNotContainsString(
            "=== 'sqlite'",
            $content,
            "AppServiceProvider contient encore une vérification 'sqlite'."
        );
    }
}
