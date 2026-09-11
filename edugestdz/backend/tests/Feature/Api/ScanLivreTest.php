<?php

namespace Tests\Feature\Api;

use App\Models\Livre;
use App\Models\Role;
use App\Models\User;
use App\Models\Tenant;
use App\Services\ScanLivreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ScanLivreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $tenant->id]);

        $role = Role::firstOrCreate(
            ['nom' => 'admin'],
            ['nom' => 'admin', 'description' => 'Administrateur']
        );

        $this->admin = User::factory()->create([
            'role_id'   => $role->id,
            'tenant_id' => $tenant->id,
        ]);

        App::bind(ScanLivreService::class, function () {
            $mock = new class extends ScanLivreService {
                public function estConfigured(): bool
                {
                    return true;
                }

                public function analyserImage(string $imageData): array
                {
                    return [
                        'success'   => true,
                        'texte'     => "Le Petit Prince\nAntoine de Saint-Exupéry\nISBN: 9782070612758",
                        'titre'     => 'Le Petit Prince',
                        'auteur'    => 'Antoine de Saint-Exupéry',
                        'isbn'      => '9782070612758',
                        'confiance' => 95.2,
                    ];
                }
            };

            return $mock;
        });
    }

    public function test_scan_livre_trouve_dans_catalogue(): void
    {
        $livre = Livre::create([
            'titre'          => 'Le Petit Prince',
            'auteur'         => 'Antoine de Saint-Exupéry',
            'isbn'           => '9782070612758',
            'nb_exemplaires' => 3,
            'nb_disponibles' => 2,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image' => base64_encode('fake_image_data'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source'  => 'catalogue',
            ])
            ->assertJsonPath('data.livre.id', $livre->id)
            ->assertJsonPath('data.disponible', true);
    }

    public function test_scan_livre_inconnu_retourne_ocr(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image' => base64_encode('fake_image_data'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source'  => 'scan',
                'data'    => null,
            ])
            ->assertJsonPath('ocr.titre', 'Le Petit Prince')
            ->assertJsonPath('ocr.isbn', '9782070612758');
    }

    public function test_scan_sans_image_retourne_422(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', []);

        $response->assertUnprocessable();
    }

    public function test_scan_sans_auth_retourne_401(): void
    {
        $response = $this->postJson('/api/v1/bibliotheque/scan', [
            'image' => base64_encode('fake_image_data'),
        ]);

        $response->assertUnauthorized();
    }

    public function test_scan_avec_url_image(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image_url' => 'https://example.com/book_cover.jpg',
            ]);

        $response->assertOk()
            ->assertJsonPath('source', 'scan');
    }
}
