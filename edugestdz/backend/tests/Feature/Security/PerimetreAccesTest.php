<?php

namespace Tests\Feature\Security;

use App\Models\Bulletin;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Groupe;
use App\Models\Matiere;
use App\Models\ParentEleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerimetreAccesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Sprint 2 (suite) — Périmètre d'accès intra-tenant.
 *
 * Le RBAC par route empêche un parent d'appeler /paies. Ces tests couvrent
 * l'étage suivant : un parent QUI A LE DROIT d'appeler /bulletins ne doit y
 * voir que les bulletins de SES enfants — pas ceux de tout l'établissement.
 *
 * De même, un enseignant ne doit voir que les élèves de SES groupes.
 */
class PerimetreAccesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private PerimetreAccesService $perimetre;

    private User  $parent;
    private User  $enseignant;
    private User  $admin;

    private Eleve $enfantDuParent;
    private Eleve $eleveDuGroupe;
    private Eleve $eleveEtranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->perimetre = app(PerimetreAccesService::class);
        $this->tenant    = Tenant::factory()->create(['statut' => 'actif']);

        config(['tenant.current_id' => $this->tenant->id]);

        $this->parent     = $this->creerUtilisateur('parent');
        $this->enseignant = $this->creerUtilisateur('enseignant');
        $this->admin      = $this->creerUtilisateur('admin');

        // ── Trois élèves du même établissement ──
        $this->enfantDuParent = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->eleveDuGroupe  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->eleveEtranger  = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        // ── Filiation : parent -> enfantDuParent ──
        $fiche = ParentEleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->parent->id,
        ]);

        DB::table('eleve_parent')->insert([
            'eleve_id'      => $this->enfantDuParent->id,
            'parent_id'     => $fiche->id,
            'est_principal' => true,
        ]);

        // ── Affectation : enseignant -> groupe -> eleveDuGroupe ──
        $ficheEns = Enseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->enseignant->id,
        ]);

        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);

        $groupe = Groupe::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $ficheEns->id,
            'matiere_id'    => $matiere->id,
        ]);

        DB::table('inscriptions')->insert([
            'id'                => Str::uuid()->toString(),
            'tenant_id'         => $this->tenant->id,
            'eleve_id'          => $this->eleveDuGroupe->id,
            'groupe_id'         => $groupe->id,
            'annee_scolaire'    => '2025-2026',
            'date_inscription'  => now()->toDateString(),
            'statut'            => 'validée',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function creerUtilisateur(string $nomRole): User
    {
        $role = Role::factory()->create(['nom' => $nomRole]);

        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
    }

    private function en(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    // ══════════════════════════════════════════════════
    // SERVICE
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_voit_que_ses_enfants(): void
    {
        $autorises = $this->perimetre->elevesAutorises($this->parent);

        $this->assertIsArray($autorises);
        $this->assertContains($this->enfantDuParent->id, $autorises);
        $this->assertNotContains($this->eleveEtranger->id, $autorises);
        $this->assertNotContains($this->eleveDuGroupe->id, $autorises);
        $this->assertCount(1, $autorises);
    }

    public function test_un_enseignant_ne_voit_que_les_eleves_de_ses_groupes(): void
    {
        $autorises = $this->perimetre->elevesAutorises($this->enseignant);

        $this->assertIsArray($autorises);
        $this->assertContains($this->eleveDuGroupe->id, $autorises);
        $this->assertNotContains($this->eleveEtranger->id, $autorises);
        $this->assertNotContains($this->enfantDuParent->id, $autorises);
    }

    public function test_un_admin_a_une_vision_globale(): void
    {
        $this->assertTrue($this->perimetre->aVisionGlobale($this->admin));
        $this->assertNull(
            $this->perimetre->elevesAutorises($this->admin),
            'null signifie « aucune restriction »'
        );
    }

    public function test_peutVoirEleve_respecte_la_filiation(): void
    {
        $this->assertTrue($this->perimetre->peutVoirEleve($this->parent, $this->enfantDuParent->id));
        $this->assertFalse($this->perimetre->peutVoirEleve($this->parent, $this->eleveEtranger->id));
        $this->assertTrue($this->perimetre->peutVoirEleve($this->admin, $this->eleveEtranger->id));
    }

    /** Un parent sans fiche liée ne doit rien voir — surtout pas tout voir. */
    public function test_un_parent_sans_filiation_ne_voit_rien(): void
    {
        $orphelin = $this->creerUtilisateur('parent');

        $this->assertSame([], $this->perimetre->elevesAutorises($orphelin));
        $this->assertFalse($this->perimetre->peutVoirEleve($orphelin, $this->enfantDuParent->id));
    }

    /** Une liste vide ne doit jamais dégénérer en « tous les résultats ». */
    public function test_un_perimetre_vide_ne_retourne_aucun_resultat(): void
    {
        $orphelin = $this->creerUtilisateur('parent');

        $query = $this->perimetre->restreindreParEleve(Eleve::query(), $orphelin, 'id');

        $this->assertSame(0, $query->count());
    }

    public function test_un_utilisateur_non_authentifie_ne_voit_rien(): void
    {
        $this->assertSame([], $this->perimetre->elevesAutorises(null));
        $this->assertFalse($this->perimetre->peutVoirEleve(null, $this->enfantDuParent->id));
    }

    // ══════════════════════════════════════════════════
    // BULLETINS — le cas concret signalé au Sprint 2
    // ══════════════════════════════════════════════════

    public function test_un_parent_ne_liste_que_les_bulletins_de_ses_enfants(): void
    {
        Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->enfantDuParent->id,
        ]);
        Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->eleveEtranger->id,
        ]);

        $reponse = $this->getJson('/api/v1/bulletins', $this->en($this->parent));

        $reponse->assertOk();

        $ids = collect($reponse->json('data'))->pluck('eleve_id')->all();

        $this->assertContains($this->enfantDuParent->id, $ids);
        $this->assertNotContains($this->eleveEtranger->id, $ids);
    }

    public function test_un_parent_ne_peut_pas_ouvrir_le_bulletin_dun_autre_eleve(): void
    {
        $bulletin = Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->eleveEtranger->id,
        ]);

        $this->getJson("/api/v1/bulletins/{$bulletin->id}", $this->en($this->parent))
            ->assertStatus(403);
    }

    public function test_un_parent_peut_ouvrir_le_bulletin_de_son_enfant(): void
    {
        $bulletin = Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->enfantDuParent->id,
        ]);

        $this->getJson("/api/v1/bulletins/{$bulletin->id}", $this->en($this->parent))
            ->assertOk();
    }

    public function test_un_admin_voit_tous_les_bulletins(): void
    {
        Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->enfantDuParent->id,
        ]);
        Bulletin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->eleveEtranger->id,
        ]);

        $reponse = $this->getJson('/api/v1/bulletins', $this->en($this->admin));

        $reponse->assertOk();
        $this->assertCount(2, $reponse->json('data'));
    }

    // ══════════════════════════════════════════════════
    // DOSSIER ÉLÈVE
    // ══════════════════════════════════════════════════

    public function test_un_enseignant_ne_liste_que_les_eleves_de_ses_groupes(): void
    {
        $reponse = $this->getJson('/api/v1/eleves', $this->en($this->enseignant));

        $reponse->assertOk();

        $ids = collect($reponse->json('data'))->pluck('id')->all();

        $this->assertContains($this->eleveDuGroupe->id, $ids);
        $this->assertNotContains($this->eleveEtranger->id, $ids);
    }

    public function test_un_enseignant_ne_peut_pas_ouvrir_un_eleve_hors_groupe(): void
    {
        $this->getJson("/api/v1/eleves/{$this->eleveEtranger->id}", $this->en($this->enseignant))
            ->assertStatus(403);
    }

    public function test_un_enseignant_peut_ouvrir_un_eleve_de_son_groupe(): void
    {
        $this->getJson("/api/v1/eleves/{$this->eleveDuGroupe->id}", $this->en($this->enseignant))
            ->assertOk();
    }

    public function test_un_admin_liste_tous_les_eleves(): void
    {
        $reponse = $this->getJson('/api/v1/eleves', $this->en($this->admin));

        $reponse->assertOk();
        $this->assertGreaterThanOrEqual(3, count($reponse->json('data')));
    }

    // ══════════════════════════════════════════════════
    // CACHE
    // ══════════════════════════════════════════════════

    public function test_le_cache_de_perimetre_est_invalide_par_une_nouvelle_filiation(): void
    {
        // Amorce le cache.
        $this->assertCount(1, $this->perimetre->elevesAutorises($this->parent));

        // `parents.user_id` porte une contrainte UNIQUE : un utilisateur ne
        // peut avoir qu'une seule fiche parent. La nouvelle filiation passe
        // donc par un rattachement supplémentaire sur la fiche existante,
        // pas par la création d'une seconde fiche.
        $fiche = ParentEleve::where('user_id', $this->parent->id)->firstOrFail();

        DB::table('eleve_parent')->insert([
            'eleve_id'      => $this->eleveEtranger->id,
            'parent_id'     => $fiche->id,
            'est_principal' => false,
        ]);

        // L'observer a purgé le cache au `saved` de ParentEleve.
        $this->perimetre->oublier($this->parent);

        $autorises = $this->perimetre->elevesAutorises($this->parent);

        $this->assertContains($this->eleveEtranger->id, $autorises);
        $this->assertCount(2, $autorises);
    }
}
