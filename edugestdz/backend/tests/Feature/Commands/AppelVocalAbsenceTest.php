<?php

namespace Tests\Feature\Commands;

use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppelVocalAbsenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        config(['services.twilio.sid' => 'test-sid']);
        config(['services.twilio.token' => 'test-token']);
        config(['services.twilio.from' => '+1234567890']);
    }

    public function test_dry_run_appelle_rien(): void
    {
        Http::fake();

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence', ['--dry-run' => true])
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_appelle_parents_des_absents(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
        ]);

        $eleve = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nom'       => 'Benali',
            'prenom'    => 'Amira',
        ]);
        $parent = ParentEleve::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'telephone_1' => '0555555555',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Calls.json')
                && $request->method() === 'POST';
        });

        $this->assertDatabaseHas('absences_journalieres', [
            'eleve_id'           => $eleve->id,
            'appel_vocal_envoye' => true,
        ]);
    }

    public function test_ignore_parent_sans_telephone_valide(): void
    {
        Http::fake();

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'telephone_1' => '12345',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_sans_twilio_configure(): void
    {
        config(['services.twilio.sid' => null]);
        config(['services.twilio.token' => null]);
        config(['services.twilio.from' => null]);

        Http::fake();

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'telephone_1' => '0555555555',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_idempotence_cache(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
        ]);

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'telephone_1' => '0555555555',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);
        Http::assertSentCount(1);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);
        Http::assertSentCount(1);
    }

    public function test_force_override_cache(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
        ]);

        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $parent = ParentEleve::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'telephone_1' => '0555555555',
        ]);
        $eleve->parents()->attach($parent->id, ['est_principal' => true]);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => today(),
            'statut'       => 'absent',
        ]);

        $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);
        Http::assertSentCount(1);

        AbsenceJournaliere::where('eleve_id', $eleve->id)
            ->update(['appel_vocal_envoye' => false]);

        $this->artisan('edugest:appel-vocal-absence', ['--force' => true])->assertExitCode(0);
        Http::assertSentCount(2);
    }
}
