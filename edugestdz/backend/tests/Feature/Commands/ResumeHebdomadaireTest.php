<?php

namespace Tests\Feature\Commands;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Note;
use App\Models\Evaluation;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Groupe;
use App\Models\Matiere;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResumeHebdomadaireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $tenant->id]);
    }

    public function test_commande_envoie_le_resume(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $matiere = Matiere::factory()->create();
        $groupe = Groupe::factory()->create(['matiere_id' => $matiere->id]);
        $evaluation = Evaluation::factory()->create([
            'groupe_id' => $groupe->id,
            'date_evaluation' => now()->subDay(),
            'note_sur' => 20,
        ]);
        Note::factory()->create([
            'eleve_id' => $eleve->id,
            'evaluation_id' => $evaluation->id,
            'note' => 15,
        ]);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }

    public function test_cache_empeche_double_envoi(): void
    {
        $semaine = now()->startOfWeek();
        Cache::put("resume_hebdo_{$semaine->format('Y-m-d')}", true);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications_parent', 0);
    }

    public function test_force_bypass_cache(): void
    {
        $semaine = now()->startOfWeek();
        Cache::put("resume_hebdo_{$semaine->format('Y-m-d')}", true);

        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $matiere = Matiere::factory()->create();
        $groupe = Groupe::factory()->create(['matiere_id' => $matiere->id]);
        $evaluation = Evaluation::factory()->create([
            'groupe_id' => $groupe->id,
            'date_evaluation' => now()->subDay(),
            'note_sur' => 20,
        ]);
        Note::factory()->create([
            'eleve_id' => $eleve->id,
            'evaluation_id' => $evaluation->id,
            'note' => 15,
        ]);

        $this->artisan('edugest:resume-hebdo-parents', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }

    public function test_aucune_donnee_passe_vide(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }

    public function test_eleves_inactifs_exclus(): void
    {
        Eleve::factory()->create(['statut' => 'inactif']);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications_parent', 0);
    }

    public function test_absences_comptabilisees(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $seance = Seance::factory()->create(['date_seance' => now()->subDay()]);
        Presence::factory()->create([
            'eleve_id' => $eleve->id,
            'seance_id' => $seance->id,
            'statut' => 'absent',
        ]);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }
}
