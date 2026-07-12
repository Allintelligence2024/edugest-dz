<?php

namespace Tests\Unit\Services;

use App\Jobs\GenerateBulletinPdfJob;
use App\Models\{Bulletin, Eleve, Groupe, Matiere, Tenant};
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Tests\TestCase;

class BulletinServicePdfTest extends TestCase
{
    use RefreshDatabase;

    private BulletinService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->service = app(BulletinService::class);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    private function inscrireEleve(string $eleveId, string $groupeId): void
    {
        DB::table('inscriptions')->insert([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->id,
            'eleve_id'          => $eleveId,
            'groupe_id'         => $groupeId,
            'annee_scolaire'    => '2025-2026',
            'date_inscription'  => now()->toDateString(),
            'frais_inscription' => 0,
            'frais_paye'        => false,
            'statut'            => 'validée',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function test_generer_bulletins_dispatche_un_job_par_eleve(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve1 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve2 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->inscrireEleve($eleve1->id, $groupe->id);
        $this->inscrireEleve($eleve2->id, $groupe->id);

        $resultats = $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $this->assertCount(2, $resultats);
        Queue::assertPushed(GenerateBulletinPdfJob::class, 2);
    }

    public function test_generer_bulletins_create_des_bulletins_en_bdd(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->inscrireEleve($eleve->id, $groupe->id);

        $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $this->assertDatabaseHas('bulletins', [
            'eleve_id'       => $eleve->id,
            'groupe_id'      => $groupe->id,
            'trimestre'      => 'T1',
            'annee_scolaire' => '2025-2026',
            'tenant_id'      => $this->tenant->id,
            'statut_pdf'     => 'en_attente',
        ]);
    }

    public function test_generer_bulletins_retourne_moyennes_et_rangs(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->inscrireEleve($eleve->id, $groupe->id);

        $resultats = $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $this->assertArrayHasKey('moyenne', $resultats[0]);
        $this->assertArrayHasKey('rang', $resultats[0]);
        $this->assertArrayHasKey('bulletin_id', $resultats[0]);
        $this->assertEquals(1, $resultats[0]['rang']);
    }

    public function test_generer_bulletins_est_idempotent(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->inscrireEleve($eleve->id, $groupe->id);

        $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');
        $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $this->assertEquals(1, Bulletin::where('eleve_id', $eleve->id)->where('trimestre', 'T1')->count());
    }

    public function test_generer_bulletins_sans_eleve_retourne_vide(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);

        $resultats = $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $this->assertEmpty($resultats);
        Queue::assertNotPushed(GenerateBulletinPdfJob::class);
    }

    public function test_generer_bulletins_transaction_db_libere_rapidement(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);

        for ($i = 0; $i < 5; $i++) {
            $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
            $this->inscrireEleve($eleve->id, $groupe->id);
        }

        $start = microtime(true);
        $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(2000, $elapsed);
        $this->assertDatabaseCount('bulletins', 5);
    }

    public function test_generer_bulletins_fichier_url_reste_null(): void
    {
        $matiere = Matiere::factory()->create(['tenant_id' => $this->tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $this->tenant->id, 'matiere_id' => $matiere->id]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->inscrireEleve($eleve->id, $groupe->id);

        $this->service->genererBulletins($groupe->id, 'T1', '2025-2026');

        $bulletin = Bulletin::where('eleve_id', $eleve->id)->first();
        $this->assertNull($bulletin->fichier_url);
    }
}
