<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Sprint 2 — Escalade de privilèges intra-tenant.
 *
 * L'isolation multi-tenant (testée par TenantIsolationTest) répond à :
 *   « l'école A peut-elle voir les données de l'école B ? » → non.
 *
 * Ces tests répondent à la question complémentaire, jusqu'ici sans réponse :
 *   « dans l'école A, un parent peut-il lire les salaires du personnel ? »
 *
 * Avant le Sprint 2 la réponse était OUI pour l'essentiel de l'API.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    /** @var array<string,string> nom du rôle => JWT */
    private array $jetons = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);

        foreach (['admin', 'gestionnaire', 'enseignant', 'parent', 'comptable', 'secretariat'] as $nom) {
            $role = Role::factory()->create(['nom' => $nom]);

            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'role_id'   => $role->id,
                'statut'    => 'actif',
            ]);

            $this->jetons[$nom] = JWTAuth::fromUser($user);
        }

        config(['tenant.current_id' => $this->tenant->id]);
    }

    private function en(string $role): array
    {
        return ['Authorization' => 'Bearer ' . $this->jetons[$role]];
    }

    /** Assertion : le rôle ne doit PAS atteindre la route (403 attendu). */
    private function assertRefuse(string $role, string $methode, string $uri): void
    {
        $reponse = $this->json($methode, $uri, [], $this->en($role));

        $this->assertSame(
            403,
            $reponse->status(),
            "Le rôle « {$role} » ne devrait pas accéder à {$methode} {$uri} "
            . "(reçu {$reponse->status()})"
        );
    }

    /** Assertion : le rôle doit franchir le contrôle RBAC (pas de 403). */
    private function assertAutorise(string $role, string $methode, string $uri): void
    {
        $reponse = $this->json($methode, $uri, [], $this->en($role));

        $this->assertNotSame(
            403,
            $reponse->status(),
            "Le rôle « {$role} » devrait accéder à {$methode} {$uri} (reçu 403)"
        );
    }

    // ══════════════════════════════════════════════════
    // FINANCE — la zone la plus sensible
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_peut_pas_lire_les_paies(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/paies');
    }

    public function test_un_enseignant_ne_peut_pas_lire_les_paies(): void
    {
        $this->assertRefuse('enseignant', 'GET', '/api/v1/paies');
    }

    public function test_un_parent_ne_peut_pas_consulter_le_tableau_de_bord_financier(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/finance/tableau-bord');
    }

    public function test_un_enseignant_ne_peut_pas_lister_les_factures(): void
    {
        $this->assertRefuse('enseignant', 'GET', '/api/v1/factures');
    }

    public function test_un_parent_ne_peut_pas_consulter_la_caisse(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/paiements/caisse-jour');
    }

    public function test_le_comptable_accede_bien_a_la_finance(): void
    {
        $this->assertAutorise('comptable', 'GET', '/api/v1/finance/tableau-bord');
    }

    // ══════════════════════════════════════════════════
    // RH / PERSONNEL
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_peut_pas_lister_le_personnel(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/personnel');
    }

    public function test_un_enseignant_ne_peut_pas_lire_les_contrats(): void
    {
        $this->assertRefuse('enseignant', 'GET', '/api/v1/contrats');
    }

    // ══════════════════════════════════════════════════
    // ADMINISTRATION
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_peut_pas_lire_le_journal_daudit(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/audit-logs');
    }

    public function test_un_enseignant_ne_peut_pas_exporter_les_donnees_rgpd(): void
    {
        $this->assertRefuse('enseignant', 'GET', '/api/v1/rgpd/export-tenant');
    }

    public function test_un_parent_ne_peut_pas_modifier_les_parametres(): void
    {
        $this->assertRefuse('parent', 'PATCH', '/api/v1/parametres');
    }

    public function test_un_enseignant_ne_peut_pas_lire_les_parametres(): void
    {
        $this->assertRefuse('enseignant', 'GET', '/api/v1/parametres');
    }

    // ══════════════════════════════════════════════════
    // PÉDAGOGIE — les enseignants DOIVENT pouvoir travailler
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_peut_pas_creer_devaluation(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/evaluations');
    }

    public function test_un_enseignant_accede_aux_evaluations(): void
    {
        $this->assertAutorise('enseignant', 'GET', '/api/v1/evaluations');
    }

    public function test_un_enseignant_peut_saisir_les_presences(): void
    {
        $this->assertAutorise('enseignant', 'GET', '/api/v1/presences/rapport');
    }

    public function test_un_parent_ne_peut_pas_saisir_les_presences(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/presences/rapport');
    }

    public function test_un_enseignant_peut_lire_la_liste_des_eleves(): void
    {
        $this->assertAutorise('enseignant', 'GET', '/api/v1/eleves');
    }

    public function test_un_parent_ne_peut_pas_lister_tous_les_eleves(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/eleves');
    }

    public function test_un_enseignant_ne_peut_pas_supprimer_un_eleve(): void
    {
        $this->assertRefuse('enseignant', 'DELETE', '/api/v1/eleves/' . fake()->uuid());
    }

    // ══════════════════════════════════════════════════
    // ROUTE AUTREFOIS OUVERTE À TOUS
    // ══════════════════════════════════════════════════

    public function test_les_absences_enseignants_exigent_une_authentification(): void
    {
        // Avant le Sprint 2 : aucun middleware, accessible sans jeton.
        $this->json('GET', '/api/v1/absences-enseignants')->assertStatus(401);
    }

    public function test_un_parent_ne_peut_pas_assigner_un_remplacant(): void
    {
        $this->assertRefuse('parent', 'GET', '/api/v1/absences-enseignants');
    }

    // ══════════════════════════════════════════════════
    // SOCLE RBAC
    // ══════════════════════════════════════════════════

    public function test_hasRole_et_hasPermission(): void
    {
        $role = Role::factory()->create(['nom' => 'comptable']);
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->assertTrue($user->hasRole('comptable'));
        $this->assertTrue($user->hasRole('admin', 'comptable'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->isSuperAdmin());
        $this->assertSame('comptable', $user->nomRole());
    }

    public function test_le_super_admin_contourne_les_controles_de_role(): void
    {
        $role = Role::factory()->create(['nom' => 'super_admin']);
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
        ]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('nimporte.quoi'));
    }

    public function test_un_utilisateur_sans_role_na_aucune_permission(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => null,
        ]);

        $this->assertNull($user->nomRole());
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasPermission('eleves.lister'));
        $this->assertSame([], $user->permissions());
    }
}
