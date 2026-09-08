<?php

namespace Tests\Feature\Performance;

use App\Models\{Eleve, Enseignant, Facture, Groupe, LigneFacture, ParentEleve, Role, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BudgetRequetes;
use Tests\TestCase;

/**
 * Budget de requêtes SQL par endpoint (Sprint 4 — P1-5 / perf).
 *
 * `QueryMonitor` existait déjà mais se contentait d'écrire un avertissement
 * dans les logs : une régression N+1 passait donc la CI sans bruit. Ce test
 * transforme le budget déclaré dans `config/performance.php` en garde
 * bloquante.
 *
 * Deux mesures par endpoint :
 *
 *  1. Croissance nulle — le nombre de requêtes ne doit pas augmenter quand
 *     la collection retournée passe de 1 à 5 enregistrements. C'est la
 *     signature exacte d'un N+1, indépendamment de tout nombre magique.
 *
 *  2. Plafond absolu — filet contre l'explosion franche.
 *
 * Complémentaire de `N1QueriesTest`, qui cible des relations nommées ;
 * ici on raisonne au niveau de l'endpoint, ce qui couvre aussi les
 * relations ajoutées plus tard sans test dédié.
 */
class BudgetRequetesEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use BudgetRequetes;

    protected string $token;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role         = Role::factory()->create(['nom' => 'admin']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    /** Appel authentifié sur un endpoint de liste, échec explicite si non-200. */
    private function appeler(string $url): callable
    {
        return function () use ($url) {
            $reponse = $this->withToken($this->token)->getJson($url);

            $this->assertSame(
                200,
                $reponse->status(),
                "L'endpoint {$url} devait répondre 200, reçu {$reponse->status()}.\n"
                . 'Corps : ' . mb_substr((string) $reponse->getContent(), 0, 600)
            );

            return $reponse;
        };
    }

    // ── Élèves ────────────────────────────────────────────────────────────

    public function test_liste_eleves_ne_coute_pas_plus_cher_avec_plus_d_eleves(): void
    {
        $creer = function (int $n): void {
            Eleve::factory()->count($n)->create(['tenant_id' => $this->tenant->id])
                ->each(function (Eleve $eleve) {
                    $parent = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);
                    $eleve->parents()->attach($parent->id, ['est_principal' => true]);
                });
        };

        $this->assertPasDeN1($creer, $this->appeler('/api/v1/eleves'), 'GET /api/v1/eleves');
    }

    // ── Factures ──────────────────────────────────────────────────────────

    public function test_liste_factures_ne_coute_pas_plus_cher_avec_plus_de_factures(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $creer = function (int $n) use ($eleve): void {
            Facture::factory()->count($n)->create([
                'tenant_id' => $this->tenant->id,
                'eleve_id'  => $eleve->id,
            ])->each(function (Facture $facture) {
                LigneFacture::create([
                    'tenant_id'     => $this->tenant->id,
                    'facture_id'    => $facture->id,
                    'description'   => 'Scolarité',
                    'prix_unitaire' => 15000,
                    'quantite'      => 1,
                    'total'         => 15000,
                    'type_ligne'    => 'cours',
                ]);
            });
        };

        $this->assertPasDeN1($creer, $this->appeler('/api/v1/factures'), 'GET /api/v1/factures');
    }

    // ── Groupes ───────────────────────────────────────────────────────────

    public function test_liste_groupes_ne_coute_pas_plus_cher_avec_plus_de_groupes(): void
    {
        $creer = fn (int $n) => Groupe::factory()->count($n)->create(['tenant_id' => $this->tenant->id]);

        $this->assertPasDeN1($creer, $this->appeler('/api/v1/groupes'), 'GET /api/v1/groupes');
    }

    // ── Enseignants ───────────────────────────────────────────────────────

    public function test_liste_enseignants_ne_coute_pas_plus_cher_avec_plus_d_enseignants(): void
    {
        $creer = fn (int $n) => Enseignant::factory()->count($n)->create(['tenant_id' => $this->tenant->id]);

        $this->assertPasDeN1($creer, $this->appeler('/api/v1/enseignants'), 'GET /api/v1/enseignants');
    }

    // ── Plafonds absolus ──────────────────────────────────────────────────

    public function test_les_endpoints_de_liste_respectent_leur_plafond_de_requetes(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        Facture::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $eleve->id,
        ]);
        Groupe::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Enseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $budgets = config('performance.budgets');

        $this->assertNotEmpty($budgets, 'Aucun budget déclaré dans config/performance.php');

        foreach ($budgets as $chemin => $budget) {
            $url = '/' . ltrim($chemin, '/');

            // Chauffe : les caches partagés ne doivent pas être imputés au
            // premier endpoint mesuré.
            $this->withToken($this->token)->getJson($url);

            $this->assertBudgetRequetes($budget, $this->appeler($url), "GET {$url}");
        }
    }

    // ── Instrumentation ───────────────────────────────────────────────────

    public function test_le_middleware_expose_le_compteur_de_requetes(): void
    {
        Eleve::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $reponse = $this->withToken($this->token)->getJson('/api/v1/eleves');
        $reponse->assertOk();

        $compteur = $reponse->headers->get('X-Query-Count');
        $budget   = $reponse->headers->get('X-Query-Budget');

        $this->assertNotNull($compteur, 'En-tête X-Query-Count absent — QueryMonitor est-il toujours branché ?');
        $this->assertGreaterThan(0, (int) $compteur, 'X-Query-Count devrait compter au moins une requête.');
        $this->assertSame(
            (string) config('performance.budgets.api/v1/eleves'),
            $budget,
            'X-Query-Budget doit refléter le budget déclaré en configuration.'
        );
    }
}
