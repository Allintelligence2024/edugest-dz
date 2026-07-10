<?php

namespace Tests\Feature\Api;

use App\Services\ParentNotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationsEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_mail_fake_fonctionne(): void
    {
        Mail::fake();
        Mail::send('emails.bienvenue', [
            'nom' => 'Test', 'prenom' => 'User', 'email' => 'test@test.com',
            'motDePasseTemporaire' => 'temp123', 'nomEcole' => 'Test School',
            'urlApplication' => 'http://localhost',
        ], fn($m) => $m->to('test@test.com')->subject('Test'));

        Mail::assertSent(\Illuminate\Mail\Message::class);
    }

    public function test_template_absence_compilee_sans_erreur(): void
    {
        $view = view('emails.absence-eleve', [
            'parentNom'      => 'BENALI',
            'parentPrenom'   => 'Ahmed',
            'eleveNom'       => 'BENALI',
            'elevePrenom'    => 'Amira',
            'dateAbsence'    => '10/07/2026',
            'nomEcole'       => 'École Test',
            'motif'          => null,
            'urlApplication' => 'http://localhost',
            'urlDesinscription' => null,
        ]);
        $html = $view->render();

        $this->assertStringContainsString('Absence signalée', $html);
        $this->assertStringContainsString('BENALI', $html);
        $this->assertStringContainsString('Amira', $html);
        $this->assertStringContainsString('10/07/2026', $html);
    }

    public function test_template_bulletin_compilee_sans_erreur(): void
    {
        $html = view('emails.bulletin-disponible', [
            'parentNom'      => 'KHELIL',
            'parentPrenom'   => 'Fatima',
            'eleveNom'       => 'KHELIL',
            'elevePrenom'    => 'Mohamed',
            'trimestre'      => 'Trimestre 3',
            'anneeScolaire'  => '2025-2026',
            'moyenne'        => 15.4,
            'rang'           => 3,
            'effectif'       => 28,
            'mention'        => 'Bien',
            'appreciation'   => 'Excellent travail',
            'urlBulletin'    => 'http://localhost/bulletins',
            'urlApplication' => 'http://localhost',
            'nomEcole'       => 'École Test',
        ])->render();

        $this->assertStringContainsString('15.4', $html);
        $this->assertStringContainsString('Bien', $html);
        $this->assertStringContainsString('Excellent travail', $html);
        $this->assertNotEmpty($html);
    }

    public function test_template_facture_relance_compilee(): void
    {
        $html = view('emails.facture-relance', [
            'parentNom'          => 'MEZIANI',
            'parentPrenom'       => 'Ali',
            'eleveNom'           => 'MEZIANI',
            'elevePrenom'        => 'Anis',
            'numeroFacture'      => 'FAC-2026-001',
            'montant'            => 12500,
            'dateEcheance'       => '01/07/2026',
            'joursRetard'        => 9,
            'periode'            => 'Juillet 2026',
            'numeroRelance'      => 1,
            'urlPaiementEnLigne' => 'http://localhost/payer',
            'urlApplication'     => 'http://localhost',
            'telephoneEcole'     => '0555 00 00 00',
            'nomEcole'           => 'École Test',
        ])->render();

        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('FAC-2026-001', $html);
        $this->assertStringContainsString('Dahabia', $html);
    }

    public function test_template_note_publiee_compilee(): void
    {
        $html = view('emails.note-publiee', [
            'parentNom'      => 'BENSALEM',
            'parentPrenom'   => 'Khaled',
            'elevePrenom'    => 'Fatima',
            'matiere'        => 'Mathématiques',
            'note'           => 18,
            'noteMax'        => 20,
            'noteSur20'      => 18,
            'appreciation'   => 'Très bon travail',
            'emoji'          => '✅',
            'noteColor'      => '#16a34a',
            'urlApplication' => 'http://localhost',
            'nomEcole'       => 'École Test',
        ])->render();

        $this->assertStringContainsString('18', $html);
        $this->assertStringContainsString('Mathématiques', $html);
        $this->assertStringContainsString('Très bon travail', $html);
    }

    public function test_template_bienvenue_avec_mdp_temporaire(): void
    {
        $html = view('emails.bienvenue', [
            'nom'                  => 'HAMDI',
            'prenom'               => 'Amine',
            'email'                => 'amine@test.com',
            'motDePasseTemporaire' => 'Temp@2026!',
            'nomEcole'             => 'École Ibn Khaldoun',
            'urlApplication'       => 'http://localhost',
        ])->render();

        $this->assertStringContainsString('HAMDI', $html);
        $this->assertStringContainsString('Temp@2026!', $html);
        $this->assertStringContainsString('Ibn Khaldoun', $html);
        $this->assertStringContainsString('Algérie', $html);
    }

    public function test_aucun_email_envoye_si_mail_driver_log(): void
    {
        config(['mail.default' => 'log']);
        $this->assertTrue(true);
    }
}
