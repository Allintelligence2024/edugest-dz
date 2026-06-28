<?php
namespace Tests\Feature\Api;

use App\Models\{User, Tenant, Role};
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected User   $admin;
    protected Tenant $tenant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role         = Role::factory()->create(['nom' => 'admin']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);

        $this->token = auth('api')->login($this->admin);
    }

    public function test_enable_totp(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/auth/2fa/enable', ['type' => 'totp']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['type', 'secret', 'qr_code_url', 'recovery_codes'],
            ]);
    }

    public function test_confirm_totp(): void
    {
        $service = app(TwoFactorService::class);
        $secretData = $service->generateSecret();

        $this->admin->update([
            'two_factor_type'   => 'totp',
            'two_factor_secret' => $secretData['secret'],
        ]);

        $code = $service->verifyCode($secretData['secret'], '123456');
        $validCode = $this->generateValidTotpCode($secretData['secret']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => $validCode]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->admin->fresh()->two_factor_confirmed_at);
    }

    public function test_disable_2fa(): void
    {
        $this->admin->update([
            'two_factor_type'         => 'totp',
            'two_factor_secret'       => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'password']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $user = $this->admin->fresh();
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_secret);
    }

    public function test_login_requires_2fa(): void
    {
        $this->admin->update([
            'two_factor_type'         => 'totp',
            'two_factor_secret'       => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['temp_token', 'user_id', 'two_factor_type']);
    }

    public function test_login_completes_2fa(): void
    {
        $service = app(TwoFactorService::class);
        $secretData = $service->generateSecret();

        $this->admin->update([
            'two_factor_type'         => 'totp',
            'two_factor_secret'       => $secretData['secret'],
            'two_factor_confirmed_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);

        $loginResponse->assertJsonPath('two_factor_required', true);
        $tempToken = $loginResponse['temp_token'];

        $validCode = $this->generateValidTotpCode($secretData['secret']);

        $completeResponse = $this->postJson('/api/v1/auth/2fa/complete', [
            'temp_token' => $tempToken,
            'code'       => $validCode,
        ]);

        $completeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_recovery_code_login(): void
    {
        $service = app(TwoFactorService::class);
        $secretData = $service->generateSecret();
        $recoveryCodes = $service->generateRecoveryCodes();

        $this->admin->update([
            'two_factor_type'             => 'totp',
            'two_factor_secret'           => $secretData['secret'],
            'two_factor_confirmed_at'     => now(),
            'two_factor_recovery_codes'   => json_encode($recoveryCodes),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);

        $loginResponse->assertJsonPath('two_factor_required', true);
        $tempToken = $loginResponse['temp_token'];

        $completeResponse = $this->postJson('/api/v1/auth/2fa/complete', [
            'temp_token' => $tempToken,
            'code'       => $recoveryCodes[0],
        ]);

        $completeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['access_token', 'user']);

        $remaining = json_decode($this->admin->fresh()->two_factor_recovery_codes, true);
        $this->assertCount(7, $remaining);
    }

    public function test_lockout_after_5_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => $this->admin->email,
                'password' => 'wrong_password_' . $i,
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'wrong_password_5',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('error.code', 'ACCOUNT_LOCKED');

        $this->assertNotNull($this->admin->fresh()->locked_until);
    }

    public function test_invalid_2fa_code(): void
    {
        $this->admin->update([
            'two_factor_type'         => 'totp',
            'two_factor_secret'       => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);

        $tempToken = $loginResponse['temp_token'];

        $completeResponse = $this->postJson('/api/v1/auth/2fa/complete', [
            'temp_token' => $tempToken,
            'code'       => '000000',
        ]);

        $completeResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_2FA_CODE');
    }

    private function generateValidTotpCode(string $secret): string
    {
        $service = app(TwoFactorService::class);
        $timeSlice = (int) floor(time() / 30);

        $ref = new \ReflectionMethod($service, 'generateTOTP');
        $ref->setAccessible(true);

        return $ref->invoke($service, $secret, $timeSlice);
    }

    public function test_enable_sms(): void
    {
        $this->admin->update(['telephone' => '+213555123456']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/auth/2fa/enable', [
                'type'  => 'sms',
                'phone' => '+213555123456',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_confirm_sms(): void
    {
        $service = app(TwoFactorService::class);
        $this->admin->update([
            'two_factor_type' => 'sms',
            'two_factor_phone' => '+213555123456',
        ]);

        Cache::put('2fa_sms_otp_' . $this->admin->id, bcrypt('123456'), now()->addMinutes(5));

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => '123456']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
