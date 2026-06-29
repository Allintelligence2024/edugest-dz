<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Facture;
use App\Models\Groupe;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d'isolation multi-tenant — EduGest DZ
 *
 * Principe : un utilisateur du tenant A ne doit JAMAIS
 * voir, modifier ou supprimer une ressource du tenant B.
 *
 * Chemin cible : edugestdz/backend/tests/Feature/Api/TenantIsolationTest.php
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User   $adminA;
    private User   $adminB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nom' => 'admin']);

        // ── Tenant A ──
        $this->tenantA = Tenant::factory()->create(['statut' => 'actif']);
        $this->adminA  = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->tokenA = auth('api')->login($this->adminA);
        
        // ── Tenant B ──
        $this->tenantB = Tenant::factory()->create(['statut' => 'actif']);
        $this->adminB  = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->tokenB = auth('api')->login($this->adminB);
    }

    // ══════════════════════════════════════════════════
    // ÉLÈVES
    // ══════════════════════════════════════════════════

    /** AdminA ne voit que les élèves de son tenant */
    public function test_liste_eleves_filtree_par_tenant(): void
    {
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Eleve::factory()->count(5)->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->getJson('/api/v1/eleves')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3); // pas 8
    }

    /** AdminA ne peut pas lire un élève du tenant B */
    public function test_lecture_eleve_autre_tenant_retourne_404(): void
    {
        $eleveB = Eleve::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->getJson("/api/v1/eleves/{$eleveB->id}")
            ->assertStatus(404);
    }

    /** AdminA ne peut pas modifier un élève du tenant B */
    public function test_modification_eleve_autre_tenant_retourne_404(): void
    {
        $eleveB = Eleve::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->tokenA)
            ->putJson("/api/v1/eleves/{$eleveB->id}", ['statut' => 'inactif'])
            ->assertStatus(404);

        // Vérifie que la BDD n'a pas changé
        $this->assertDatabaseHas('eleves', [
            'id'     => $eleveB->id,
            'statut' => 'actif',
        ]);
    }

    /** AdminA ne peut pas supprimer un élève du tenant B */
    public function test_suppression_eleve_autre_tenant_retourne_404(): void
    {
        $eleveB = Eleve::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/eleves/{$eleveB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('eleves', ['id' => $eleveB->id]);
    }

    /** Un élève créé par adminA a bien le tenant_id de A */
    public function test_creation_eleve_injecte_tenant_id_correct(): void
    {
        $data = [
            'nom'            => 'BENALI',
            'prenom'         => 'Ahmed',
            'date_naissance' => '2010-01-15',
            'lieu_naissance' => 'Alger',
            'sexe'           => 'M',
            'niveau_scolaire'=> '3AS',
        ];

        $this->withToken($this->tokenA)
            ->postJson('/api/v1/eleves', $data)
            ->assertStatus(201);

        $this->assertDatabaseHas('eleves', [
            'nom'       => 'BENALI',
            'tenant_id' => $this->tenantA->id,
        ]);

        // Pas de pollution sur tenant B
        $this->assertDatabaseMissing('eleves', [
            'nom'       => 'BENALI',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    // ══════════════════════════════════════════════════
    // ENSEIGNANTS
    // ══════════════════════════════════════════════════

    /** AdminA ne voit que les enseignants de son tenant */
    public function test_liste_enseignants_filtree_par_tenant(): void
    {
        Enseignant::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Enseignant::factory()->count(4)->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->getJson('/api/v1/enseignants')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /** AdminA ne peut pas lire un enseignant du tenant B */
    public function test_lecture_enseignant_autre_tenant_retourne_404(): void
    {
        $enseignantB = Enseignant::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->getJson("/api/v1/enseignants/{$enseignantB->id}")
            ->assertStatus(404);
    }

    // ══════════════════════════════════════════════════
    // GROUPES
    // ══════════════════════════════════════════════════

    /** AdminA ne voit que les groupes de son tenant */
    public function test_liste_groupes_filtree_par_tenant(): void
    {
        Groupe::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Groupe::factory()->count(3)->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->getJson('/api/v1/groupes')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    // ══════════════════════════════════════════════════
    // PAIEMENTS
    // ══════════════════════════════════════════════════

    /** AdminA ne peut pas lire un paiement du tenant B — vecteur financier critique */
    public function test_lecture_paiement_autre_tenant_retourne_404(): void
    {
        $eleveB     = Eleve::factory()->create(['tenant_id' => $this->tenantB->id]);
        $paiementB  = Paiement::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'eleve_id'  => $eleveB->id,
        ]);

        $this->withToken($this->tokenA)
            ->getJson("/api/v1/paiements/{$paiementB->id}")
            ->assertStatus(404);
    }

    /** AdminA ne peut pas modifier un paiement du tenant B */
    public function test_modification_paiement_autre_tenant_retourne_404(): void
    {
        $eleveB    = Eleve::factory()->create(['tenant_id' => $this->tenantB->id]);
        $paiementB = Paiement::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'eleve_id'  => $eleveB->id,
            'montant'   => 5000,
        ]);

        $this->withToken($this->tokenA)
            ->putJson("/api/v1/paiements/{$paiementB->id}", ['montant' => 1])
            ->assertStatus(404);

        $this->assertDatabaseHas('paiements', [
            'id'      => $paiementB->id,
            'montant' => 5000,
        ]);
    }

    // ══════════════════════════════════════════════════
    // FACTURES
    // ══════════════════════════════════════════════════

    /** AdminA ne peut pas télécharger le PDF d'une facture du tenant B */
    public function test_pdf_facture_autre_tenant_retourne_404(): void
    {
        $eleveB   = Eleve::factory()->create(['tenant_id' => $this->tenantB->id]);
        $factureB = Facture::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'eleve_id'  => $eleveB->id,
        ]);

        $this->withToken($this->tokenA)
            ->getJson("/api/v1/factures/{$factureB->id}/pdf")
            ->assertStatus(404);
    }

    // ══════════════════════════════════════════════════
    // SUPER-ADMIN — ne doit PAS être accessible aux admins normaux
    // ══════════════════════════════════════════════════

    /** Un admin normal ne peut pas accéder aux routes super-admin */
    public function test_admin_normal_ne_peut_pas_acceder_super_admin(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    /** Un admin normal ne peut pas voir les stats globales */
    public function test_admin_normal_ne_peut_pas_voir_stats_globales(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/v1/super-admin/stats')
            ->assertStatus(403);
    }

    /** Un admin normal ne peut pas usurper un autre tenant */
    public function test_impersonate_bloque_pour_admin_normal(): void
    {
        $this->withToken($this->tokenA)
            ->postJson("/api/v1/super-admin/tenants/{$this->tenantB->id}/impersonate")
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════
    // INJECTION FORCÉE — attaque directe
    // ══════════════════════════════════════════════════

    /**
     * Un utilisateur malveillant qui injecte un tenant_id B
     * dans le body d'une requête de création ne doit pas
     * pouvoir créer une ressource dans le tenant B.
     */
    public function test_injection_tenant_id_dans_body_ignoree(): void
    {
        $data = [
            'nom'            => 'HACKER',
            'prenom'         => 'Test',
            'date_naissance' => '2010-01-15',
            'lieu_naissance' => 'Alger',
            'sexe'           => 'M',
            'niveau_scolaire'=> '3AS',
            'tenant_id'      => $this->tenantB->id, // injection forcée
        ];

        $this->withToken($this->tokenA)
            ->postJson('/api/v1/eleves', $data)
            ->assertStatus(201);

        // La ressource doit être dans tenant A, pas tenant B
        $this->assertDatabaseHas('eleves', [
            'nom'       => 'HACKER',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->assertDatabaseMissing('eleves', [
            'nom'       => 'HACKER',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Requête sans token → 401 sur toutes les routes protégées.
     * Un oubli de middleware expose toute la base.
     */
    public function test_requete_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/eleves')->assertStatus(401);
        $this->getJson('/api/v1/enseignants')->assertStatus(401);
        $this->getJson('/api/v1/paiements')->assertStatus(401);
        $this->getJson('/api/v1/factures')->assertStatus(401);
        $this->getJson('/api/v1/groupes')->assertStatus(401);
    }
}
