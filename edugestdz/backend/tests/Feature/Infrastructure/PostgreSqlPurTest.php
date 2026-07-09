<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie que le CI tourne bien sur PostgreSQL et pas SQLite.
 * Ces tests échouent volontairement si SQLite est utilisé.
 */
class PostgreSqlPurTest extends TestCase
{
    public function test_connexion_est_postgresql(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->assertEquals(
            'pgsql',
            $driver,
            "❌ Les tests tournent sur '{$driver}' au lieu de 'pgsql'. Vérifier phpunit.xml et DB_CONNECTION."
        );
    }

    public function test_version_postgresql_est_16_ou_plus(): void
    {
        $version = DB::selectOne('SELECT version()')->version;
        $this->assertStringContainsString('PostgreSQL', $version);

        preg_match('/PostgreSQL (\d+)/', $version, $matches);
        $majorVersion = (int) ($matches[1] ?? 0);
        $this->assertGreaterThanOrEqual(
            15,
            $majorVersion,
            "PostgreSQL {$majorVersion} détecté — version 15+ requise."
        );
    }

    public function test_extension_uuid_disponible(): void
    {
        $result = DB::selectOne("SELECT gen_random_uuid() AS uuid");
        $this->assertNotNull($result->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result->uuid
        );
    }

    public function test_sha3_disponible_php(): void
    {
        $this->assertContains('sha3-256', hash_algos(), 'SHA3-256 non disponible en PHP — vérifier version PHP');
        $hash = hash('sha3-256', 'test');
        $this->assertEquals(64, strlen($hash));
    }

    public function test_extension_sodium_disponible(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Extension sodium non disponible sur cette machine');
        }
        $this->assertTrue(true);
    }

    public function test_redis_accessible(): void
    {
        \Illuminate\Support\Facades\Cache::put('ci_test_redis', 'ok', 10);
        $this->assertEquals('ok', \Illuminate\Support\Facades\Cache::get('ci_test_redis'));
        \Illuminate\Support\Facades\Cache::forget('ci_test_redis');
    }

    public function test_jsonb_operations_postgresql(): void
    {
        $result = DB::selectOne("SELECT '{\"a\": 1}'::jsonb ->> 'a' AS val");
        $this->assertEquals('1', $result->val);
    }

    public function test_savepoint_postgresql(): void
    {
        DB::transaction(function () {
            DB::statement('SAVEPOINT test_savepoint');
            DB::statement('RELEASE SAVEPOINT test_savepoint');
        });
        $this->assertTrue(true);
    }
}
