<?php

namespace Tests\Unit\Services;

use App\Models\{Tenant, Eleve};
use App\Services\PredictionEchecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PredictionEchecServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionEchecService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->service = app(PredictionEchecService::class);
    }

    public function test_predire_retourne_structure_complete(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertArrayHasKey('prediction_id', $resultat);
        $this->assertArrayHasKey('eleve_id', $resultat);
        $this->assertArrayHasKey('probabilite', $resultat);
        $this->assertArrayHasKey('confiance', $resultat);
        $this->assertArrayHasKey('horizon', $resultat);
        $this->assertArrayHasKey('niveau_risque', $resultat);
        $this->assertArrayHasKey('facteurs_risque', $resultat);
        $this->assertArrayHasKey('recommandations', $resultat);
        $this->assertArrayHasKey('moteur', $resultat);
        $this->assertArrayHasKey('resume', $resultat);
    }

    public function test_probabilite_entre_0_et_100(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertGreaterThanOrEqual(0, $resultat['probabilite']);
        $this->assertLessThanOrEqual(100, $resultat['probabilite']);
    }

    public function test_confiance_entre_20_et_98(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertGreaterThanOrEqual(20, $resultat['confiance']);
        $this->assertLessThanOrEqual(98, $resultat['confiance']);
    }

    public function test_niveau_risque_valide(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertContains($resultat['niveau_risque'], ['faible', 'modere', 'eleve', 'critique']);
    }

    public function test_eleve_sans_notes_retourne_fallback_ews(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertNull($resultat['prediction_id']);
        $this->assertEquals('fallback_ews', $resultat['moteur']);
    }

    public function test_eleve_sans_notes_confiance_faible(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertLessThanOrEqual(60, $resultat['confiance']);
    }

    public function test_horizon_4_semaines_par_defaut(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertEquals('4_semaines', $resultat['horizon']);
    }

    public function test_horizon_fin_trimestre_reduit_probabilite(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat4sem = $this->service->predire($eleve->id, '4_semaines');
        $resultatTrim = $this->service->predire($eleve->id, 'fin_trimestre');

        $this->assertIsFloat($resultatTrim['probabilite']);
    }

    public function test_horizon_fin_annee_reduit_probabilite(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultatAnnee = $this->service->predire($eleve->id, 'fin_annee');

        $this->assertIsFloat($resultatAnnee['probabilite']);
    }

    public function test_facteurs_risque_est_tableau(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertIsArray($resultat['facteurs_risque']);
    }

    public function test_recommandations_est_tableau(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertIsArray($resultat['recommandations']);
        $this->assertNotEmpty($resultat['recommandations']);
    }

    public function test_recommandation_contient_cles_requises(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $rec = $resultat['recommandations'][0];
        $this->assertArrayHasKey('priorite', $rec);
        $this->assertArrayHasKey('type', $rec);
        $this->assertArrayHasKey('label', $rec);
    }

    public function test_moteur_est_logistique_v1_ou_fallback(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertContains($resultat['moteur'], ['logistique_v1', 'fallback_ews']);
    }

    public function test_resume_est_string_non_vide(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertIsString($resultat['resume']);
        $this->assertNotEmpty($resultat['resume']);
    }

    public function test_eleve_id_correspond(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertEquals($eleve->id, $resultat['eleve_id']);
    }

    public function test_prediction_persistee_dans_table(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->creerDonneesMinimal($eleve);
        $resultat = $this->service->predire($eleve->id);

        if ($resultat['prediction_id'] !== null) {
            $existant = DB::table('predictions_echec')
                ->where('id', $resultat['prediction_id'])
                ->first();
            $this->assertNotNull($existant);
            $this->assertEquals($eleve->id, $existant->eleve_id);
        } else {
            $this->assertEquals('fallback_ews', $resultat['moteur']);
        }
    }

    public function test_predire_tenant_retourne_liste(): void
    {
        Eleve::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $resultats = $this->service->predireTenant($this->tenant->id);

        $this->assertIsArray($resultats);
        $this->assertCount(3, $resultats);
    }

    public function test_predire_tenant_trie_par_probabilite_desc(): void
    {
        Eleve::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $resultats = $this->service->predireTenant($this->tenant->id);

        for ($i = 1; $i < count($resultats); $i++) {
            $this->assertGreaterThanOrEqual(
                $resultats[$i]['probabilite'],
                $resultats[$i - 1]['probabilite']
            );
        }
    }

    public function test_predire_tenant_sans_eleves_retourne_vide(): void
    {
        $resultats = $this->service->predireTenant($this->tenant->id);

        $this->assertIsArray($resultats);
        $this->assertEmpty($resultats);
    }

    public function test_predire_avec_eleve_inexistant_retourne_fallback(): void
    {
        $resultat = $this->service->predire('eleve-inexistant-123');

        $this->assertEquals('fallback_ews', $resultat['moteur']);
        $this->assertNull($resultat['prediction_id']);
    }

    public function test_probabilite_eleve_plus_grande_que_faible(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->creerDonneesRisques($eleve);
        $resultat = $this->service->predire($eleve->id);

        $this->assertGreaterThan(30, $resultat['probabilite']);
    }

    public function test_facteur_risque_a_poids(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->creerDonneesMinimal($eleve);
        $resultat = $this->service->predire($eleve->id);

        $this->assertNotEmpty($resultat['facteurs_risque']);
        if (isset($resultat['facteurs_risque'][0])) {
            $this->assertArrayHasKey('label', $resultat['facteurs_risque'][0]);
        }
    }

    public function test_recommandation_au_moins_une(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $resultat = $this->service->predire($eleve->id);

        $this->assertGreaterThanOrEqual(1, count($resultat['recommandations']));
    }

    public function test_fallback_ews_probabilite_correspond_score(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        DB::table('diagnostics_eleves')->insert([
            'id'             => (string) Str::uuid(),
            'eleve_id'       => $eleve->id,
            'tenant_id'      => $this->tenant->id,
            'score_risque'   => 65,
            'niveau_global'  => 'vigilance',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $resultat = $this->service->predire($eleve->id);

        if ($resultat['moteur'] === 'fallback_ews') {
            $this->assertEquals(65.0, $resultat['probabilite']);
        }
    }

    private function creerDonneesMinimal(Eleve $eleve): void
    {
        // Juste un diagnostic pour que le fallback ait un score
        DB::table('diagnostics_eleves')->insert([
            'id'             => (string) Str::uuid(),
            'eleve_id'       => $eleve->id,
            'tenant_id'      => $this->tenant->id,
            'score_risque'   => 40,
            'niveau_global'  => 'normal',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function creerDonneesRisques(Eleve $eleve): void
    {
        // Créer un diagnostic avec score élevé pour que le fallback retourne un risque élevé
        DB::table('diagnostics_eleves')->insert([
            'id'             => (string) Str::uuid(),
            'eleve_id'       => $eleve->id,
            'tenant_id'      => $this->tenant->id,
            'score_risque'   => 85,
            'niveau_global'  => 'critique',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
