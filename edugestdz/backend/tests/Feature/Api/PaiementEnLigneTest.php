<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaiementEnLigneTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;
    private Eleve  $eleve;
    private Facture $facture;

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

        $this->eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->eleve->id,
            'total_ttc' => 5000,
            'statut'    => 'émise',
        ]);

        Config::set('satim.sandbox', true);
    }

    // ─── INITIER PAIEMENT ────────────────────────────────

    public function test_initier_paiement_cib_sandbox(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['paiement', 'redirect_url', 'order_id']]);

        $this->assertStringContainsString('retour', $response->json('data.redirect_url'));

        $this->assertDatabaseHas('paiements', [
            'facture_id'   => $this->facture->id,
            'statut'       => 'en_attente',
            'mode'         => 'en_ligne',
            'type_paiement'=> 'cib',
        ]);
    }

    public function test_initier_paiement_dahabia_sandbox(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'dahabia',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_initier_paiement_baridimob_retourne_reference(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'baridimob',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['paiement', 'reference', 'montant', 'instructions']]);

        $this->assertNotEmpty($response->json('data.reference'));
    }

    public function test_initier_facture_deja_payee_bloque(): void
    {
        $this->facture->update(['statut' => 'payée']);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'E004');
    }

    public function test_initier_facture_autre_tenant_bloque(): void
    {
        $autreTenant  = Tenant::factory()->create();
        $autreEleve   = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        $autreFacture = Facture::factory()->create([
            'tenant_id' => $autreTenant->id,
            'eleve_id'  => $autreEleve->id,
            'total_ttc' => 3000,
            'statut'    => 'émise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $autreFacture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(404);
    }

    // ─── RETOUR SATIM ─────────────────────────────────────

    public function test_retour_sandbox_confirme_paiement(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-TEST123456',
            'order_id'       => 'SANDBOX_TEST001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->getJson('/api/v1/paiements/online/retour?reference=PAY-TEST123456&satim_order_id=SANDBOX_TEST001')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'confirmé',
        ]);

        $this->assertDatabaseHas('factures', [
            'id'     => $this->facture->id,
            'statut' => 'payée',
        ]);
    }

    public function test_retour_echec_annule_paiement(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-ECHEC001',
            'order_id'       => null,
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->getJson('/api/v1/paiements/online/retour?reference=PAY-ECHEC001&echec=1')
            ->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PAYMENT_CANCELLED');

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'annulé',
        ]);
    }

    // ─── DASHBOARD ───────────────────────────────────────

    public function test_dashboard_paiements_en_ligne(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['periode', 'stats' => ['total_transactions', 'confirmes', 'montant_total', 'sandbox_actif']],
            ]);
    }

    public function test_dashboard_affiche_sandbox_actif(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard')
            ->assertStatus(200);

        $this->assertTrue($response->json('data.stats.sandbox_actif'));
    }

    // ─── REMBOURSEMENT ───────────────────────────────────

    public function test_rembourser_paiement_confirme(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-REMB001',
            'order_id'       => 'SANDBOX_REMB001',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Erreur de saisie — doublon de paiement',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'remboursé',
        ]);
    }

    public function test_rembourser_paiement_non_confirme_bloque(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-ATT001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Test remboursement paiement non confirmé',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_CONFIRMED');
    }

    // ─── VÉRIFICATION STATUT ─────────────────────────────

    public function test_verifier_statut_paiement_sandbox(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-STAT001',
            'order_id'       => 'SANDBOX_STAT001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paiements/online/{$paiement->id}/statut")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['paiement', 'satim_response', 'statut_satim']]);
    }

    // ─── CALLBACK ────────────────────────────────────────

    public function test_callback_confirme_paiement_sandbox(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-CB001',
            'order_id'       => 'SANDBOX_CB001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->postJson('/api/v1/paiements/online/callback', [
            'orderId'     => 'SANDBOX_CB001',
            'orderNumber' => 'PAY-CB001',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'confirmé',
        ]);
    }

    public function test_callback_parametres_manquants_400(): void
    {
        $this->postJson('/api/v1/paiements/online/callback', [])
            ->assertStatus(400);
    }

    public function test_callback_transaction_introuvable_404(): void
    {
        $this->postJson('/api/v1/paiements/online/callback', [
            'orderId'     => 'INEXISTANT',
            'orderNumber' => 'PAY-FAKE',
        ])
            ->assertStatus(404);
    }

    public function test_callback_deja_confirme_422(): void
    {
        Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-DJAC001',
            'order_id'       => 'SANDBOX_DJAC001',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->postJson('/api/v1/paiements/online/callback', [
            'orderId'     => 'SANDBOX_DJAC001',
            'orderNumber' => 'PAY-DJAC001',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'E004');
    }

    // ─── DASHBOARD AVEC DONNÉES ──────────────────────────

    public function test_dashboard_avec_paiements_confirme(): void
    {
        Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 3000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-DASH001',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard')
            ->assertStatus(200);

        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.total_transactions'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.confirmes'));
        $this->assertGreaterThan(0, $response->json('data.stats.montant_total'));
    }

    public function test_dashboard_filtre_par_mois(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard?mois=6&annee=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.periode.mois', 6)
            ->assertJsonPath('data.periode.annee', 2026);
    }

    // ─── REMBOURSEMENT AVEC MISE À JOUR FACTURE ─────────

    public function test_rembourser_remet_facture_en_emise(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-REMB2',
            'order_id'       => 'SANDBOX_REMB2',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->facture->update(['statut' => 'payée']);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Annulation cours — remboursement',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('factures', [
            'id'     => $this->facture->id,
            'statut' => 'émise',
        ]);
    }

    public function test_rembourser_enregistre_date_et_motif(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-REMB3',
            'order_id'       => 'SANDBOX_REMB3',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Doublon de paiement détecté',
            ])
            ->assertStatus(200);

        $paiement->refresh();
        $this->assertNotNull($paiement->rembourse_le);
        $this->assertEquals('Doublon de paiement détecté', $paiement->motif_remboursement);
    }

    // ─── VÉRIFICATION STATUT SANS ORDER_ID ──────────────

    public function test_verifier_statut_sans_order_id_422(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'baridimob',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'baridimob',
            'reference_trans'=> 'PAY-STATN001',
            'order_id'       => null,
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paiements/online/{$paiement->id}/statut")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ORDER_ID');
    }

    // ─── VÉRIFICATION STATUT CONFIRME SATELITE ──────────

    public function test_verifier_statut_confirme_satim_maj_bdd(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-STATCONF001',
            'order_id'       => 'SANDBOX_STATCONF001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paiements/online/{$paiement->id}/statut")
            ->assertStatus(200);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'confirmé',
        ]);
    }

    // ─── MODELS ACCESSEURS ──────────────────────────────

    public function test_paiement_type_label_cib(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-LABEL001',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->assertEquals('CIB (Carte Interbancaire)', $paiement->type_label);
        $this->assertTrue($paiement->est_en_ligne);
        $this->assertFalse($paiement->est_rembourse);
    }

    public function test_paiement_est_rembourse_apres_remboursement(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-REMBLABEL',
            'order_id'       => 'SANDBOX_REMBLABEL',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Test accesseur remboursé',
            ])
            ->assertStatus(200);

        $paiement->refresh();
        $this->assertTrue($paiement->est_rembourse);
    }
}
