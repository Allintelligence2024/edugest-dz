<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CheckConfigCommandTest extends TestCase
{
    public function test_command_exists_and_returns_status_code(): void
    {
        $exitCode = Artisan::call('edugest:check-config', ['--secrets-only' => true]);
        $this->assertIsInt($exitCode);
    }

    public function test_command_output_contains_key_sections(): void
    {
        Artisan::call('edugest:check-config');

        $output = Artisan::output();

        $this->assertStringContainsString('EduGest DZ', $output);
        $this->assertStringContainsString('Résumé', $output);
    }

    public function test_command_detects_empty_app_key(): void
    {
        $original = config('app.key');
        config(['app.key' => '']);

        Artisan::call('edugest:check-config', ['--secrets-only' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('APP_KEY non définie', $output);

        config(['app.key' => $original]);
    }

    public function test_command_detects_empty_jwt_secret(): void
    {
        $original = config('jwt.secret');
        config(['jwt.secret' => '']);

        Artisan::call('edugest:check-config', ['--secrets-only' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('JWT_SECRET non définie', $output);

        config(['jwt.secret' => $original]);
    }

    public function test_command_passes_with_valid_config(): void
    {
        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'jwt.secret' => str_repeat('b', 32),
            'app.timezone' => 'Africa/Algiers',
            'cache.default' => 'redis',
            'mail.from.address' => 'test@edugestdz.dz',
            'database.default' => 'pgsql',
        ]);

        $exitCode = Artisan::call('edugest:check-config', ['--secrets-only' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Configuration valide', $output);
    }

    public function test_command_fails_when_app_key_is_short(): void
    {
        config([
            'app.key' => 'short',
            'jwt.secret' => str_repeat('b', 32),
        ]);

        $exitCode = Artisan::call('edugest:check-config', ['--secrets-only' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('trop courte', $output);
    }

    public function test_secrets_only_flag_skips_optional_services(): void
    {
        Artisan::call('edugest:check-config', ['--secrets-only' => true]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('Satim', $output);
        $this->assertStringNotContainsString('Twilio', $output);
        $this->assertStringNotContainsString('Firebase', $output);
        $this->assertStringNotContainsString('WhatsApp', $output);
    }
}
