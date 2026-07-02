<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerTest extends TestCase
{
    /**
     * Le fichier api-docs.json est bien généré et valide.
     */
    public function test_swagger_json_exists_and_is_valid(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $this->assertFileExists($docsPath, 'api-docs.json doit être généré');

        $json = json_decode(file_get_contents($docsPath), true);
        $this->assertNotNull($json, 'api-docs.json doit être du JSON valide');
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertArrayHasKey('paths', $json);
    }

    /**
     * La documentation couvre les endpoints critiques.
     */
    public function test_swagger_covers_critical_endpoints(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $json  = json_decode(file_get_contents($docsPath), true);
        $paths = array_keys($json['paths'] ?? []);

        $critical = [
            '/api/v1/auth/login',
            '/api/v1/eleves',
            '/api/v1/finance/tableau-bord',
            '/api/v1/transport/circuits',
            '/api/v1/budget/dashboard',
        ];

        foreach ($critical as $endpoint) {
            $this->assertContains(
                $endpoint,
                $paths,
                "L'endpoint {$endpoint} doit être documenté dans Swagger"
            );
        }
    }

    /**
     * La sécurité JWT est définie.
     */
    public function test_swagger_defines_jwt_security(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $json = json_decode(file_get_contents($docsPath), true);

        $schemes = $json['components']['securitySchemes'] ?? [];
        $this->assertArrayHasKey('bearerAuth', $schemes, 'bearerAuth doit être défini');
        $this->assertSame('http',   $schemes['bearerAuth']['type']);
        $this->assertSame('bearer', $schemes['bearerAuth']['scheme']);
    }
}
