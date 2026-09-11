<?php

namespace App\Mail;

use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Facture $facture,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "EduGest DZ — Facture {$this->facture->numero_facture}",
        );
    }

    public function content(): Content
    {
        $eleve  = $this->facture->eleve;
        $parent = $eleve?->parents?->firstWhere('pivot.est_principal', true);

        $joursRetard = $this->facture->date_echeance
            ? max(0, now()->diffInDays($this->facture->date_echeance, false))
            : 0;

        return new Content(
            view: 'emails.facture-relance',
            with: [
                'numeroRelance'     => 1,
                'nomEcole'          => config('app.name', 'EduGest DZ'),
                'parentPrenom'      => $parent->prenom ?? '',
                'parentNom'         => $parent->nom ?? '',
                'numeroFacture'     => $this->facture->numero_facture,
                'elevePrenom'       => $eleve->prenom ?? '',
                'eleveNom'          => $eleve->nom ?? '',
                'periode'           => $this->facture->mois . '/' . $this->facture->annee,
                'dateEcheance'      => $this->facture->date_echeance?->format('d/m/Y') ?? '',
                'joursRetard'       => $joursRetard,
                'montant'           => $this->facture->total_ttc,
                'urlPaiementEnLigne'=> config('app.url') . '/dashboard/factures',
                'urlApplication'    => config('app.url') . '/dashboard',
                'telephoneEcole'    => '',
            ],
        );
    }
}
