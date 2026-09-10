<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateBulletinPdfJob;
use App\Models\{Bulletin, Cours, Eleve, Enseignant, Groupe, Matiere, Tenant};
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage, Log};
use Tests\TestCase;

class GenerateBulletinPdfJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_job_est_place_sur_la_file_pdf(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $tenant->id]);
        $bulletin = Bulletin::factory()->create(['tenant_id' => $tenant->id]);

        GenerateBulletinPdfJob::dispatch($bulletin);

        Queue::assertPushed(GenerateBulletinPdfJob::class, 1);
        Queue::assertPushed(GenerateBulletinPdfJob::class, function ($job) {
            return $job->queue === 'pdf';
        });
    }

    public function test_job_met_a_jour_statut_pdf_sur_succes(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $tenant->id]);

        $matiere = Matiere::factory()->create(['tenant_id' => $tenant->id]);
        $groupe = Groupe::factory()->create(['tenant_id' => $tenant->id, 'matiere_id' => $matiere->id]);
        $enseignant = Enseignant::factory()->create(['tenant_id' => $tenant->id]);
        $eleve = Eleve::factory()->create(['tenant_id' => $tenant->id, 'numero_inscription' => 'ECO-001']);

        $bulletin = Bulletin::factory()->create([
            'tenant_id'      => $tenant->id,
            'eleve_id'       => $eleve->id,
            'groupe_id'      => $groupe->id,
            'trimestre'      => 'T1',
            'annee_scolaire' => '2025-2026',
            'statut_pdf'     => 'en_attente',
            'fichier_url'    => null,
        ]);

        $fakeService = \Mockery::mock(BulletinService::class);
        $fakeService->shouldReceive('genererPDF')->once()->andReturn('bulletins/test.pdf');
        $this->app->instance(BulletinService::class, $fakeService);

        $job = new GenerateBulletinPdfJob($bulletin);
        $job->handle();

        $bulletin->refresh();
        $this->assertEquals('bulletins/test.pdf', $bulletin->fichier_url);
        $this->assertEquals('genere', $bulletin->statut_pdf);
    }

    public function test_job_met_a_jour_statut_erreur_sur_echec(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $tenant->id]);

        $bulletin = Bulletin::factory()->create([
            'tenant_id'  => $tenant->id,
            'statut_pdf' => 'en_attente',
        ]);

        $fakeService = \Mockery::mock(BulletinService::class);
        $fakeService->shouldReceive('genererPDF')->once()->andReturn('');
        $this->app->instance(BulletinService::class, $fakeService);

        Log::shouldReceive('error')->once();

        $job = new GenerateBulletinPdfJob($bulletin);
        $job->handle();

        $bulletin->refresh();
        $this->assertEquals('erreur', $bulletin->statut_pdf);
    }
}
