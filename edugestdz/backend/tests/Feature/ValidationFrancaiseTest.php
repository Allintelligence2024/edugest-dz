<?php
namespace Tests\Feature;

use App\Models\{Tenant, User, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationFrancaiseTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role  = Role::factory()->create(['nom' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_champ_requis_message_en_francais(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/eleves', []);

        $response->assertStatus(422);

        $errors = $response->json('errors') ?? $response->json('error.details') ?? [];
        $this->assertNotEmpty($errors);

        $firstError = collect($errors)->first();
        $this->assertIsArray($firstError);
        $this->assertNotEmpty($firstError);

        $message = $firstError[0];
        $this->assertNotEmpty($message);

        $frenchWords = ['obligatoire', 'requis', 'Le champ', 'la valeur', 'invalide'];
        $containsFrench = false;
        foreach ($frenchWords as $word) {
            if (stripos($message, $word) !== false) {
                $containsFrench = true;
                break;
            }
        }
        $this->assertTrue($containsFrench, "Le message de validation n'est pas en français : {$message}");
    }

    public function test_email_invalide_message_en_francais(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/eleves', [
                'nom'             => 'Test',
                'prenom'          => 'User',
                'date_naissance'  => '2010-01-01',
                'lieu_naissance'  => 'Alger',
                'sexe'            => 'M',
                'niveau_scolaire' => '3AS',
                'email'           => 'pas-un-email',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $errors = $response->json('error.details');
        $this->assertArrayHasKey('email', $errors);

        $message = $errors['email'][0] ?? '';
        $this->assertNotEmpty($message);

        $frenchWords = ['e-mail', 'adresse', 'valide', 'format'];
        $containsFrench = false;
        foreach ($frenchWords as $word) {
            if (stripos($message, $word) !== false) {
                $containsFrench = true;
                break;
            }
        }
        $this->assertTrue($containsFrench, "Le message email n'est pas en français : {$message}");
    }

    public function test_404_message_en_francais(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/eleves/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => [
                    'code' => 'NOT_FOUND',
                ],
            ]);

        $message = $response->json('error.message');
        $this->assertNotEmpty($message);

        $frenchWords = ['introuvable', 'existe pas', 'trouvé', 'Ressource'];
        $containsFrench = false;
        foreach ($frenchWords as $word) {
            if (stripos($message, $word) !== false) {
                $containsFrench = true;
                break;
            }
        }
        $this->assertTrue($containsFrench, "Le message 404 n'est pas en français : {$message}");
    }

    public function test_405_message_en_francais(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/eleves');

        $response->assertStatus(405)
            ->assertJson([
                'success' => false,
                'error'   => [
                    'code' => 'METHOD_NOT_ALLOWED',
                ],
            ]);

        $message = $response->json('error.message');
        $this->assertNotEmpty($message);

        $frenchWords = ['méthode', 'autorisee', 'autorisée', 'URL'];
        $containsFrench = false;
        foreach ($frenchWords as $word) {
            if (stripos($message, $word) !== false) {
                $containsFrench = true;
                break;
            }
        }
        $this->assertTrue($containsFrench, "Le message 405 n'est pas en français : {$message}");
    }

    public function test_validation_exception_format_coherent(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/eleves', ['nom' => '']);

        $response->assertStatus(422);

        $body = $response->json();
        $hasNativeErrors = isset($body['errors']) && is_array($body['errors']);
        $hasCustomError = isset($body['error']['details']) && is_array($body['error']['details']);
        $this->assertTrue(
            $hasNativeErrors || $hasCustomError,
            'La réponse doit contenir errors (natif) ou error.details (custom)'
        );
    }
}
