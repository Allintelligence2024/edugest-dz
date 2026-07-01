<?php

namespace Tests\Feature\Api;

use App\Models\ArticleStock;
use App\Models\MouvementStock;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInventaireTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_creer_article_avec_reference_auto(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/articles', [
                'nom'            => 'Chaise élève',
                'categorie'      => 'mobilier',
                'unite'          => 'pièce',
                'quantite_stock' => 30,
                'quantite_minimum'=> 5,
                'valeur_unitaire'=> 3500,
                'est_immobilise' => true,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['article', 'qr_code', 'reference']]);

        $this->assertDatabaseHas('articles_stock', [
            'nom'       => 'Chaise élève',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('mouvements_stock', [
            'type'           => 'entree',
            'quantite'       => 30,
            'quantite_avant' => 0,
            'quantite_apres' => 30,
        ]);
    }

    public function test_liste_articles_filtree_par_tenant(): void
    {
        ArticleStock::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $autreTenant = Tenant::factory()->create();
        ArticleStock::factory()->count(5)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/articles')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_trouver_article_par_qr_code(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'qr_code'   => 'ART-TEST1234',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/articles/qr/ART-TEST1234')
            ->assertStatus(200)
            ->assertJsonPath('data.article.id', $article->id);
    }

    public function test_mouvement_entree_augmente_stock(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 10,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'entree',
                'quantite' => 20,
                'motif'    => 'Livraison fournisseur',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.quantite_apres', 30);
    }

    public function test_mouvement_sortie_diminue_stock(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 15,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 5,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.quantite_apres', 10);
    }

    public function test_sortie_stock_insuffisant_bloque(): void
    {
        $article = ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 3,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/stock/articles/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STOCK_INSUFFISANT');
    }

    public function test_alertes_articles_sous_seuil(): void
    {
        ArticleStock::factory()->enAlerte()->create(['tenant_id' => $this->tenant->id]);
        ArticleStock::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'quantite_stock' => 20,
            'quantite_minimum'=> 5,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/alertes')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_alertes', 1);
    }

    public function test_dashboard_stock(): void
    {
        ArticleStock::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/stock/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['alertes_stock', 'prets_en_retard', 'bons_pendants', 'valeur_totale_da'],
            ]);
    }

    public function test_isolation_tenant_article(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreArticle = ArticleStock::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/stock/articles/{$autreArticle->id}")
            ->assertStatus(404);
    }

    public function test_creer_bon_commande(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/stock/bons-commande', [
                'fournisseur'    => 'Fournitures Alger SARL',
                'date_commande'  => today()->toDateString(),
                'lignes'         => [
                    ['designation' => 'Craies blanches x100', 'quantite' => 10, 'prix_unitaire' => 500],
                    ['designation' => 'Marqueurs effaçables', 'quantite' => 5,  'prix_unitaire' => 1200],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['numero', 'lignes']]);
    }
}
