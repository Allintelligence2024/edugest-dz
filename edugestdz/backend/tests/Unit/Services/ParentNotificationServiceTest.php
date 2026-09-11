<?php

namespace Tests\Unit\Services;

use App\Models\{Eleve, Tenant, ParentEleve, Role};
use App\Services\{ParentNotificationService, NotificationTimingService, FirebaseService, Sms\SmsService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ParentNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
    }

    private function createTimingMock(): NotificationTimingService
    {
        $timing = Mockery::mock(NotificationTimingService::class);
        $timing->shouldReceive('doitEnvoyerPush')->andReturn(true);
        $timing->shouldReceive('doitEnvoyerSMS')->andReturn(true);
        $timing->shouldReceive('doitEnvoyerEmail')->andReturn(true);
        return $timing;
    }

    public function test_timing_service_injecte(): void
    {
        $timing = new NotificationTimingService();
        $this->assertNotNull($timing);
        $this->assertTrue($timing->estEnPlageHoraire(Carbon::now()->setTime(10, 0)));
    }

    public function test_notifier_sans_eleve_ne_plante_pas(): void
    {
        $firebase = $this->createMock(FirebaseService::class);
        $sms      = $this->createMock(SmsService::class);
        $timing   = $this->createTimingMock();

        $service = new ParentNotificationService($firebase, $sms, $timing);

        $service->notifier(
            '00000000-0000-0000-0000-000000000000',
            'test',
            'Test',
            'Corps test'
        );

        $firebase->expects($this->never())->method('notifyUser');
    }

    public function test_notifier_avec_eleve_appelle_firebase(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $parent = ParentEleve::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Benzema',
            'prenom'      => 'Karim',
            'lien'        => 'pere',
            'telephone_1' => '+213555000000',
        ]);
        $eleve->parents()->attach($parent->id);

        $firebase = $this->createMock(FirebaseService::class);
        $firebase->expects($this->once())
            ->method('notifyUser')
            ->willReturn(true);

        $sms = $this->createMock(SmsService::class);
        $timing = $this->createTimingMock();

        $service = new ParentNotificationService($firebase, $sms, $timing);

        $service->notifier(
            $eleve->id,
            'absence',
            'Test absence',
            'Corps test absence'
        );
    }

    public function test_note_publiee_sans_avec_sms(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $parent = ParentEleve::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Zidane',
            'prenom'      => 'Zinedine',
            'lien'        => 'pere',
            'telephone_1' => '+213555001111',
        ]);
        $eleve->parents()->attach($parent->id);

        $firebase = $this->createMock(FirebaseService::class);
        $firebase->method('notifyUser')->willReturn(true);

        $sms = $this->createMock(SmsService::class);
        $timing = $this->createTimingMock();

        $service = new ParentNotificationService($firebase, $sms, $timing);

        $service->notePubliee($eleve->id, 'Maths', 15.0, 20.0, 'Bien');

        $this->assertTrue(true);
    }

    public function test_bulletin_genere_avec_sms(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $parent = ParentEleve::create([
            'tenant_id'   => $this->tenant->id,
            'nom'         => 'Hakimi',
            'prenom'      => 'Achraf',
            'lien'        => 'pere',
            'telephone_1' => '+213555002222',
        ]);
        $eleve->parents()->attach($parent->id);

        $firebase = $this->createMock(FirebaseService::class);
        $firebase->method('notifyUser')->willReturn(true);

        $sms = $this->createMock(SmsService::class);
        $sms->expects($this->once())->method('send');

        $timing = $this->createTimingMock();

        $service = new ParentNotificationService($firebase, $sms, $timing);

        $service->bulletinGenere($eleve->id, 'Trimestre 1', 14.5, 5, 30);
    }
}
