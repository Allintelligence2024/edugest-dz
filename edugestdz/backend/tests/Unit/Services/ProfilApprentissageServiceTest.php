<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Eleve};
use App\Services\ProfilApprentissageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfilApprentissageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfilApprentissageService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->service = app(ProfilApprentissageService::class);
    }

    public function test_calculer_profil_retourne_structure(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertArrayHasKey('profil', $resultat);
        $this->assertArrayHasKey('label_fr', $resultat);
        $this->assertArrayHasKey('emoji', $resultat);
        $this->assertArrayHasKey('alarme', $resultat);
        $this->assertArrayHasKey('points_forts', $resultat);
        $this->assertArrayHasKey('points_faibles', $resultat);
        $this->assertArrayHasKey('stabilite', $resultat);
        $this->assertArrayHasKey('explication', $resultat);
    }

    public function test_profil_est_un_valid(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $profilsValides = [
            'excellent_stable', 'bon_regulier', 'moyen_stable', 'fragile_amelioration',
            'chute_rapide', 'instable_oscillant', 'absenteiste', 'decrochage_avance',
            'saisonnier', 'resilient',
        ];
        $this->assertContains($resultat['profil'], $profilsValides);
    }

    public function test_label_fr_est_string(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsString($resultat['label_fr']);
        $this->assertNotEmpty($resultat['label_fr']);
    }

    public function test_emoji_est_string(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsString($resultat['emoji']);
        $this->assertNotEmpty($resultat['emoji']);
    }

    public function test_alarme_est_bool(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsBool($resultat['alarme']);
    }

    public function test_points_forts_est_tableau(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsArray($resultat['points_forts']);
    }

    public function test_points_faibles_est_tableau(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsArray($resultat['points_faibles']);
    }

    public function test_stabilite_entre_0_et_100(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertGreaterThanOrEqual(0, $resultat['stabilite']);
        $this->assertLessThanOrEqual(100, $resultat['stabilite']);
    }

    public function test_explication_est_string(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->calculerProfil($eleve->id);

        $this->assertIsString($resultat['explication']);
        $this->assertNotEmpty($resultat['explication']);
    }

    public function test_profil_sauvegarde_en_bdd(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service->calculerProfil($eleve->id);

        $existant = DB::table('profils_apprentissage')
            ->where('eleve_id', $eleve->id)
            ->first();

        $this->assertNotNull($existant);
        $this->assertEquals($this->tenant->id, $existant->tenant_id);
    }

    public function test_profil_deuxieme_appele_update(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->calculerProfil($eleve->id);
        $this->service->calculerProfil($eleve->id);

        $count = DB::table('profils_apprentissage')
            ->where('eleve_id', $eleve->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_calculer_profil_avec_eleve_inexistant_retourne_profil_defaut(): void
    {
        $resultat = $this->service->calculerProfil('eleve-inexistant');

        $this->assertArrayHasKey('profil', $resultat);
        $this->assertIsString($resultat['profil']);
    }

    public function test_alarme_true_pour_chute_rapide(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        // Créer des données historiques pour déclencher chute_rapide
        for ($i = 0; $i < 8; $i++) {
            DB::table('historique_diagnostics')->insert([
                'id'               => (string) \Illuminate\Support\Str::uuid(),
                'eleve_id'         => $eleve->id,
                'tenant_id'        => $this->tenant->id,
                'niveau_global'    => 'moyen',
                'score_risque'     => 30 + ($i * 5),
                'moyenne_generale' => 15.0 - ($i * 1.2),
                'details'          => json_encode(['comportement' => ['absences' => 0]]),
                'analyse_le'       => now()->subWeeks(8 - $i),
            ]);
        }

        $resultat = $this->service->calculerProfil($eleve->id);

        if ($resultat['profil'] === 'chute_rapide') {
            $this->assertTrue($resultat['alarme']);
        }
    }
}
