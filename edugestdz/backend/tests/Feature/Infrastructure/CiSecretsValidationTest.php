<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class CiSecretsValidationTest extends TestCase
{
    public function test_app_env_est_testing(): void
    {
        $this->assertEquals('testing', app()->environment());
    }

    public function test_app_key_est_definie(): void
    {
        $key = config('app.key');
        $this->assertNotEmpty($key);
        $this->assertStringStartsWith('base64:', $key);
    }

    public function test_db_connection_est_pgsql(): void
    {
        $this->assertEquals('pgsql', config('database.default'));
    }

    public function test_db_database_contient_test(): void
    {
        $dbName = config('database.connections.pgsql.database');
        $this->assertStringContainsString(
            'test', $dbName,
            "La base de données de test doit contenir 'test' dans son nom (ex: edugestdz_test). " .
            "Actuel: {$dbName}. " .
            "Vérifier phpunit.xml ou les GitHub Secrets TEST_DB_DATABASE."
        );
    }

    public function test_mail_driver_est_array_en_test(): void
    {
        $this->assertEquals('array', config('mail.default'));
    }

    public function test_cache_store_est_array_en_test(): void
    {
        $this->assertEquals('array', config('cache.default'));
    }

    public function test_sentry_desactive_en_test(): void
    {
        $this->assertEmpty(config('sentry.dsn', ''));
    }

    public function test_queue_est_sync_en_test(): void
    {
        $this->assertEquals('sync', config('queue.default'));
    }
}
