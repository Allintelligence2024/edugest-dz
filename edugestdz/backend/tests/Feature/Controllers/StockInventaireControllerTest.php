<?php
namespace Tests\Feature\Controllers;

use App\Models\{ArticleStock, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInventaireControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_lister_articles(): void
    {
        ArticleStock::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/articles')
            ->assertStatus(200);
    }

    public function test_creer_article(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/articles', [
                'nom' => 'Craie blanche',
                'reference' => 'CRA-001',
                'categorie' => 'fourniture_bureau',
                'quantite_stock' => 100,
                'seuil_alerte' => 20,
                'prix_unitaire' => 150,
                'unite' => 'boîte',
            ])
            ->assertStatus(201);
    }

    public function test_creer_article_sans_nom_echoue(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/articles', ['categorie' => 'fourniture_bureau'])
            ->assertStatus(422);
    }

    public function test_afficher_article(): void
    {
        $article = ArticleStock::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/stock/articles/{$article->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.article.id', $article->id);
    }

    public function test_enregistrer_entree_stock(): void
    {
        $article = ArticleStock::factory()->create(['tenant_id' => $this->tenant->id, 'quantite_stock' => 50]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type' => 'entree',
                'quantite' => 20,
                'motif' => 'Livraison fournisseur',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('articles_stock', [
            'id' => $article->id,
            'quantite_stock' => 70,
        ]);
    }

    public function test_enregistrer_sortie_stock(): void
    {
        $article = ArticleStock::factory()->create(['tenant_id' => $this->tenant->id, 'quantite_stock' => 50]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type' => 'sortie',
                'quantite' => 10,
                'motif' => 'Utilisation classe',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('articles_stock', [
            'id' => $article->id,
            'quantite_stock' => 40,
        ]);
    }

    public function test_sortie_impossible_si_stock_insuffisant(): void
    {
        $article = ArticleStock::factory()->create(['tenant_id' => $this->tenant->id, 'quantite_stock' => 5]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type' => 'sortie',
                'quantite' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_lister_prets(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/stock/prets')
            ->assertStatus(200);
    }

    public function test_lister_bons_commande(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/stock/bons-commande')
            ->assertStatus(200);
    }
}
