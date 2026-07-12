<?php

namespace Tests\Feature\Email;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class TemplatesEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ── Bienvenue ──────────────────────────────────────────────────────

    public function test_bienvenue_template_rend_sans_erreur(): void
    {
        $html = View::make('emails.bienvenue', [
            'prenom'               => 'Yacine',
            'nom'                  => 'Brahimi',
            'nomEcole'             => 'EduGest DZ',
            'email'                => 'yacine@test.com',
            'motDePasseTemporaire' => 'Abc123!',
            'urlApplication'       => 'https://app.test',
        ])->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Bienvenue sur EduGest DZ', $html);
    }

    public function test_bienvenue_template_contient_infos_connexion(): void
    {
        $html = View::make('emails.bienvenue', [
            'prenom'               => 'Yacine',
            'nom'                  => 'Brahimi',
            'nomEcole'             => 'EduGest DZ',
            'email'                => 'yacine@test.com',
            'motDePasseTemporaire' => 'Abc123!',
            'urlApplication'       => 'https://app.test',
        ])->render();

        $this->assertStringContainsString('Yacine', $html);
        $this->assertStringContainsString('Brahimi', $html);
        $this->assertStringContainsString('yacine@test.com', $html);
        $this->assertStringContainsString('Abc123!', $html);
    }

    // ── Absence ────────────────────────────────────────────────────────

    public function test_absence_template_rend_sans_erreur(): void
    {
        $html = View::make('emails.absence-eleve', [
            'parentPrenom'  => 'Karim',
            'parentNom'     => 'Benzema',
            'elevePrenom'   => 'Sofiane',
            'eleveNom'      => 'Benzema',
            'dateAbsence'   => '12/07/2026',
            'nomEcole'      => 'EduGest DZ',
            'motif'         => 'Raison familiale',
            'urlApplication'=> 'https://app.test',
            'urlDesinscription' => null,
        ])->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Absence signalée', $html);
    }

    public function test_absence_template_contient_eleve(): void
    {
        $html = View::make('emails.absence-eleve', [
            'parentPrenom'  => 'Karim',
            'parentNom'     => 'Benzema',
            'elevePrenom'   => 'Sofiane',
            'eleveNom'      => 'Benzema',
            'dateAbsence'   => '12/07/2026',
            'nomEcole'      => 'EduGest DZ',
            'motif'         => 'Raison familiale',
            'urlApplication'=> 'https://app.test',
            'urlDesinscription' => null,
        ])->render();

        $this->assertStringContainsString('Sofiane', $html);
        $this->assertStringContainsString('Benzema', $html);
        $this->assertStringContainsString('12/07/2026', $html);
    }

    public function test_absence_template_sans_motif(): void
    {
        $html = View::make('emails.absence-eleve', [
            'parentPrenom'  => 'Karim',
            'parentNom'     => 'Benzema',
            'elevePrenom'   => 'Sofiane',
            'eleveNom'      => 'Benzema',
            'dateAbsence'   => '12/07/2026',
            'nomEcole'      => 'EduGest DZ',
            'motif'         => null,
            'urlApplication'=> 'https://app.test',
            'urlDesinscription' => null,
        ])->render();

        $this->assertStringContainsString('Aucun motif renseigné', $html);
    }

    // ── Bulletin ───────────────────────────────────────────────────────

    public function test_bulletin_template_rend_sans_erreur(): void
    {
        $html = View::make('emails.bulletin-disponible', [
            'parentPrenom'  => 'Zinedine',
            'parentNom'     => 'Zidane',
            'elevePrenom'   => 'Enzo',
            'eleveNom'      => 'Zidane',
            'trimestre'     => 'Trimestre 1',
            'anneeScolaire' => '2026-2027',
            'moyenne'       => 14.5,
            'rang'          => 5,
            'effectif'      => 30,
            'mention'       => 'Bien',
            'appreciation'  => 'Bon trimestre',
            'urlBulletin'   => 'https://app.test/bulletins',
            'urlApplication'=> 'https://app.test',
            'nomEcole'      => 'EduGest DZ',
        ])->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Bulletin Trimestre 1 disponible', $html);
    }

    public function test_bulletin_template_contient_notes(): void
    {
        $html = View::make('emails.bulletin-disponible', [
            'parentPrenom'  => 'Zinedine',
            'parentNom'     => 'Zidane',
            'elevePrenom'   => 'Enzo',
            'eleveNom'      => 'Zidane',
            'trimestre'     => 'Trimestre 1',
            'anneeScolaire' => '2026-2027',
            'moyenne'       => 14.5,
            'rang'          => 5,
            'effectif'      => 30,
            'mention'       => 'Bien',
            'appreciation'  => 'Bon trimestre',
            'urlBulletin'   => 'https://app.test/bulletins',
            'urlApplication'=> 'https://app.test',
            'nomEcole'      => 'EduGest DZ',
        ])->render();

        $this->assertStringContainsString('14.5/20', $html);
        $this->assertStringContainsString('5/30', $html);
        $this->assertStringContainsString('Bien', $html);
    }

    // ── Facture ────────────────────────────────────────────────────────

    public function test_facture_template_rend_sans_erreur(): void
    {
        $html = View::make('emails.facture-relance', [
            'numeroRelance'      => 1,
            'nomEcole'           => 'EduGest DZ',
            'parentPrenom'       => 'Achraf',
            'parentNom'          => 'Hakimi',
            'numeroFacture'      => 'FAC-2026-001',
            'elevePrenom'        => 'Noussair',
            'eleveNom'           => 'Hakimi',
            'periode'            => '7/2026',
            'dateEcheance'       => '15/07/2026',
            'joursRetard'        => 3,
            'montant'            => 45000,
            'urlPaiementEnLigne' => 'https://app.test/factures',
            'urlApplication'     => 'https://app.test',
            'telephoneEcole'     => '0555000000',
        ])->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Facture en attente de paiement', $html);
    }

    public function test_facture_template_contient_montant(): void
    {
        $html = View::make('emails.facture-relance', [
            'numeroRelance'      => 1,
            'nomEcole'           => 'EduGest DZ',
            'parentPrenom'       => 'Achraf',
            'parentNom'          => 'Hakimi',
            'numeroFacture'      => 'FAC-2026-001',
            'elevePrenom'        => 'Noussair',
            'eleveNom'           => 'Hakimi',
            'periode'            => '7/2026',
            'dateEcheance'       => '15/07/2026',
            'joursRetard'        => 3,
            'montant'            => 45000,
            'urlPaiementEnLigne' => 'https://app.test/factures',
            'urlApplication'     => 'https://app.test',
            'telephoneEcole'     => '0555000000',
        ])->render();

        $this->assertStringContainsString('FAC-2026-001', $html);
        $this->assertStringContainsString('45 000', $html);
        $this->assertStringContainsString('3j de retard', $html);
    }

    public function test_facture_template_relance_3(): void
    {
        $html = View::make('emails.facture-relance', [
            'numeroRelance'      => 3,
            'nomEcole'           => 'EduGest DZ',
            'parentPrenom'       => 'Achraf',
            'parentNom'          => 'Hakimi',
            'numeroFacture'      => 'FAC-2026-002',
            'elevePrenom'        => 'Noussair',
            'eleveNom'           => 'Hakimi',
            'periode'            => '7/2026',
            'dateEcheance'       => '01/07/2026',
            'joursRetard'        => 11,
            'montant'            => 30000,
            'urlPaiementEnLigne' => 'https://app.test/factures',
            'urlApplication'     => 'https://app.test',
            'telephoneEcole'     => '0555000000',
        ])->render();

        $this->assertStringContainsString('Dernier rappel avant suspension', $html);
    }

    // ── Note ───────────────────────────────────────────────────────────

    public function test_note_template_rend_sans_erreur(): void
    {
        $html = View::make('emails.note-publiee', [
            'parentPrenom'  => 'Zinedine',
            'elevePrenom'   => 'Enzo',
            'matiere'       => 'Mathématiques',
            'nomEcole'      => 'EduGest DZ',
            'note'          => 16,
            'noteMax'       => 20,
            'noteSur20'     => 16,
            'noteColor'     => '#16a34a',
            'emoji'         => '✅',
            'appreciation'  => 'Très bien',
            'urlApplication'=> 'https://app.test',
        ])->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Nouvelle note publiée', $html);
    }

    public function test_note_template_contient_note(): void
    {
        $html = View::make('emails.note-publiee', [
            'parentPrenom'  => 'Zinedine',
            'elevePrenom'   => 'Enzo',
            'matiere'       => 'Mathématiques',
            'nomEcole'      => 'EduGest DZ',
            'note'          => 16,
            'noteMax'       => 20,
            'noteSur20'     => 16,
            'noteColor'     => '#16a34a',
            'emoji'         => '✅',
            'appreciation'  => 'Très bien',
            'urlApplication'=> 'https://app.test',
        ])->render();

        $this->assertStringContainsString('16', $html);
        $this->assertStringContainsString('Mathématiques', $html);
        $this->assertStringContainsString('Très bien', $html);
    }
}
