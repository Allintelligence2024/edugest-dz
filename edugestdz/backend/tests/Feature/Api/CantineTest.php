<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\InscriptionCantine;
use App\Models\MenuCantine;
use App\Models\Role;
use App\Models\StockCuisine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CantineTest extends TestCase
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

    public function test_creer_menu(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/menus', [
                'date_repas'     => today()->addDay()->toDateString(),
                'plat_principal' => 'Couscous au poulet',
                'accompagnement' => 'Legumes',
                'dessert'        => 'Fruit',
                'prix_unitaire'  => 250,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.plat_principal', 'Couscous au poulet');

        $this->assertDatabaseHas('menus_cantine', [
            'plat_principal' => 'Couscous au poulet',
            'tenant_id'      => $this->tenant->id,
        ]);
    }

    public function test_menu_semaine_structure(): void
    {
        $dates = [
            now()->startOfWeek()->addDay()->toDateString(),
            now()->startOfWeek()->addDays(2)->toDateString(),
            now()->startOfWeek()->addDays(3)->toDateString(),
        ];
        foreach ($dates as $i => $date) {
            MenuCantine::factory()->create([
                'tenant_id'  => $this->tenant->id,
                'date_repas' => $date,
                'type_repas' => 'dejeuner',
            ]);
        }

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/menus/semaine')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['semaine_debut', 'semaine_fin', 'jours']]);
    }

    public function test_isolation_tenant_menus(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreMenu   = MenuCantine::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/cantine/menus/{$autreMenu->id}", ['plat_principal' => 'Hack'])
            ->assertStatus(404);
    }

    public function test_inscrire_eleve_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id'        => $eleve->id,
                'type_abonnement' => 'mensuel',
                'regime'          => 'sans_porc',
                'date_debut'      => today()->toDateString(),
                'tarif_mensuel'   => 3000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.inscription.regime', 'sans_porc');

        $this->assertDatabaseHas('inscriptions_cantine', [
            'eleve_id'  => $eleve->id,
            'regime'    => 'sans_porc',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_double_inscription_bloquee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        InscriptionCantine::create([
            'tenant_id'       => $this->tenant->id,
            'eleve_id'        => $eleve->id,
            'type_abonnement' => 'mensuel',
            'regime'          => 'normal',
            'actif'           => true,
            'date_debut'      => today()->toDateString(),
            'tarif_mensuel'   => 3000,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/inscriptions', [
                'eleve_id'        => $eleve->id,
                'type_abonnement' => 'mensuel',
                'regime'          => 'vegetarien',
                'date_debut'      => today()->toDateString(),
                'tarif_mensuel'   => 3000,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_INSCRIT');
    }

    public function test_liste_inscrits_filtree_par_tenant(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        InscriptionCantine::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'type_abonnement' => 'mensuel', 'regime' => 'normal',
            'actif' => true, 'date_debut' => today()->toDateString(), 'tarif_mensuel' => 3000,
        ]);

        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        InscriptionCantine::create([
            'tenant_id' => $autreTenant->id, 'eleve_id' => $autreEleve->id,
            'type_abonnement' => 'mensuel', 'regime' => 'normal',
            'actif' => true, 'date_debut' => today()->toDateString(), 'tarif_mensuel' => 3000,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/inscriptions')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_pointer_repas(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/pointage', [
                'date'       => today()->toDateString(),
                'type_repas' => 'dejeuner',
                'pointages'  => [
                    ['eleve_id' => $eleve->id, 'present' => true],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.presents', 1);

        $this->assertDatabaseHas('repas_journaliers', [
            'eleve_id' => $eleve->id,
            'present'  => true,
        ]);
    }

    public function test_pointage_date_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/pointage/' . today()->toDateString())
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['date', 'menu', 'repas', 'stats']]);
    }

    public function test_ajouter_article_stock(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/cantine/stock', [
                'article'        => 'Poulet frais',
                'categorie'      => 'viandes',
                'unite'          => 'kg',
                'quantite_stock' => 50,
                'seuil_alerte'   => 10,
                'prix_unitaire'  => 650,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.article', 'Poulet frais');
    }

    public function test_sortie_stock_diminue_quantite(): void
    {
        $article = StockCuisine::create([
            'tenant_id'      => $this->tenant->id,
            'article'        => 'Tomates',
            'categorie'      => 'legumes',
            'unite'          => 'kg',
            'quantite_stock' => 20,
            'seuil_alerte'   => 5,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/cantine/stock/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 8,
                'motif'    => 'Repas du jour',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.nouveau_stock', 12);
    }

    public function test_stock_insuffisant_bloque(): void
    {
        $article = StockCuisine::create([
            'tenant_id'      => $this->tenant->id,
            'article'        => 'Farine',
            'categorie'      => 'cereales',
            'unite'          => 'kg',
            'quantite_stock' => 3,
            'seuil_alerte'   => 5,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/cantine/stock/{$article->id}/mouvement", [
                'type'     => 'sortie',
                'quantite' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STOCK_INSUFFISANT');
    }

    public function test_alertes_stock(): void
    {
        StockCuisine::create([
            'tenant_id' => $this->tenant->id, 'article' => 'Huile',
            'categorie' => 'condiments', 'unite' => 'litre',
            'quantite_stock' => 2, 'seuil_alerte' => 5,
        ]);

        StockCuisine::create([
            'tenant_id' => $this->tenant->id, 'article' => 'Sel',
            'categorie' => 'condiments', 'unite' => 'kg',
            'quantite_stock' => 20, 'seuil_alerte' => 2,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/stock/alertes')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_alertes', 1);
    }

    public function test_dashboard_cantine_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/cantine/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'date', 'menu_du_jour', 'inscrits_actifs',
                    'presents_aujourdhui', 'taux_presence',
                    'par_regime', 'alertes_stock', 'ca_mois', 'menus_semaine',
                ],
            ]);
    }

    public function test_desinscrire_eleve_cantine(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $inscription = InscriptionCantine::create([
            'tenant_id'       => $this->tenant->id,
            'eleve_id'        => $eleve->id,
            'type_abonnement' => 'mensuel',
            'regime'          => 'normal',
            'actif'           => true,
            'date_debut'      => today()->toDateString(),
            'tarif_mensuel'   => 3000,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/cantine/inscriptions/{$inscription->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('inscriptions_cantine', [
            'id'     => $inscription->id,
            'actif'  => false,
        ]);
    }
}
