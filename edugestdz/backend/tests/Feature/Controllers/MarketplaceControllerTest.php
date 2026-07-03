<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\Eleve;
use App\Models\Tenant;
use App\Models\Role;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $parent;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif', 'plan_abonnement' => 'pro']);

        $roleAdmin  = Role::factory()->create(['nom' => 'admin']);
        $roleParent = Role::factory()->create(['nom' => 'parent']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleAdmin->id,
        ]);

        $this->parent = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $roleParent->id,
        ]);

        config(['tenant.current_id' => $this->tenant->id]);
    }

    public function test_recherche_publique_sans_auth(): void
    {
        ProfilMarketplace::create([
            'tenant_id'          => Str::uuid(),
            'nom_etablissement'  => 'Centre Avenir Oran',
            'adresse'            => '12 Rue des Frères Benali',
            'wilaya'             => 'Oran',
            'matieres_enseignees'=> ['Mathématiques', 'Physique'],
            'niveaux_couverts'   => ['1AS', '2AS', '3AS'],
            'visible'            => true,
        ]);

        $this->getJson('/api/v1/marketplace/recherche?wilaya=Oran')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['centres', 'total']]);
    }

    public function test_recherche_par_matiere(): void
    {
        $this->getJson('/api/v1/marketplace/recherche?matiere=Mathématiques')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_featured_sans_auth(): void
    {
        $this->getJson('/api/v1/marketplace/featured')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_profil_public_centre_visible(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id'         => $tenantId,
            'nom_etablissement' => 'Centre Test',
            'adresse'           => '1 Rue Test',
            'wilaya'            => 'Alger',
            'visible'           => true,
        ]);

        $this->getJson("/api/v1/marketplace/centres/{$tenantId}")
            ->assertStatus(200)
            ->assertJsonPath('data.nom_etablissement', 'Centre Test');
    }

    public function test_profil_centre_invisible_retourne_404(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id'         => $tenantId,
            'nom_etablissement' => 'Centre Caché',
            'adresse'           => 'Adresse',
            'wilaya'            => 'Alger',
            'visible'           => false,
        ]);

        $this->getJson("/api/v1/marketplace/centres/{$tenantId}")
            ->assertStatus(404);
    }

    public function test_voir_mon_profil_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/mon-profil')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_mettre_a_jour_profil(): void
    {
        ProfilMarketplace::create([
            'tenant_id'         => $this->tenant->id,
            'nom_etablissement' => 'Mon Centre',
            'adresse'           => '1 Rue Test',
            'wilaya'            => 'Alger',
        ]);

        $this->actingAs($this->admin, 'api')
            ->putJson('/api/v1/marketplace/mon-profil', [
                'nom_etablissement'   => 'Centre Mis à Jour',
                'description'         => 'Le meilleur centre',
                'tarif_heure_min'     => 500,
                'tarif_heure_max'     => 1200,
                'matieres_enseignees' => ['Mathématiques', 'Physique'],
                'accepte_essai_gratuit' => true,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_mettre_a_jour_profil_sans_auth_echoue(): void
    {
        $this->putJson('/api/v1/marketplace/mon-profil', ['nom_etablissement' => 'Test'])
            ->assertStatus(401);
    }

    public function test_creer_offre_cours(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/marketplace/offres-cours', [
                'titre'        => 'Cours Maths 3AS',
                'matiere'      => 'Mathématiques',
                'niveaux'      => ['3AS'],
                'type'         => 'individuel',
                'tarif_heure'  => 800,
                'essai_gratuit'=> true,
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_creer_offre_sans_titre_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/marketplace/offres-cours', ['matiere' => 'Maths', 'tarif_heure' => 500])
            ->assertStatus(422);
    }

    public function test_lister_offres_centre(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/offres-cours')
            ->assertStatus(200);
    }

    public function test_parent_peut_reserver(): void
    {
        $offre = OffreCours::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'tarif_heure' => 800,
            'active'      => true,
        ]);
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/reserver', [
                'offre_id'       => $offre->id,
                'eleve_id'       => $eleve->id,
                'date_souhaitee' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'type'           => 'cours_unique',
                'message_parent' => 'Bonjour, nous souhaitons prendre un cours.',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_reservation_date_passee_echoue(): void
    {
        $offre = OffreCours::factory()->create(['tenant_id' => $this->tenant->id, 'active' => true]);
        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/reserver', [
                'offre_id'       => $offre->id,
                'eleve_id'       => $eleve->id,
                'date_souhaitee' => now()->subDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422);
    }

    public function test_parent_voit_ses_reservations(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/marketplace/parent/reservations')
            ->assertStatus(200);
    }

    public function test_soumettre_avis(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id' => $tenantId, 'nom_etablissement' => 'Centre Test',
            'adresse' => '', 'wilaya' => 'Oran', 'visible' => true,
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/avis-centre', [
                'tenant_id'  => $tenantId,
                'note'       => 5,
                'titre'      => 'Excellent centre !',
                'commentaire'=> 'Mon fils a beaucoup progressé.',
            ])
            ->assertStatus(201);
    }

    public function test_note_invalide_echoue(): void
    {
        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/avis-centre', [
                'tenant_id' => Str::uuid(),
                'note'      => 6,
            ])
            ->assertStatus(422);
    }

    public function test_ajouter_favori(): void
    {
        $tenantId = (string) Str::uuid();
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_retirer_favori_toggle(): void
    {
        $tenantId = (string) Str::uuid();
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200);
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200);
    }

    public function test_stats_marketplace_admin(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'profils_actifs', 'profils_verifies', 'total_reservations',
            ]]);
    }
}
