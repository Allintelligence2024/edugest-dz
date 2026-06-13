<?php
namespace App\Services;

use App\Models\{Facture, LigneFacture, Paiement, Eleve, Inscription};
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class FacturationService
{
    public function creerFacture(array $data): Facture
    {
        return DB::transaction(function () use ($data) {
            $tenantId = config('tenant.current_id');

            $numero = $this->genererNumeroFacture($tenantId);

            $lignes    = $data['lignes'] ?? [];
            $sousTotal = collect($lignes)->sum('total');
            $remise    = ($data['remise_pct'] ?? 0) * $sousTotal / 100;
            $totalTTC  = $sousTotal - $remise - ($data['remise_montant'] ?? 0);

            $facture = Facture::create([
                'tenant_id'       => $tenantId,
                'numero_facture'  => $numero,
                'eleve_id'        => $data['eleve_id'],
                'mois'            => $data['mois'] ?? now()->month,
                'annee'           => $data['annee'] ?? now()->year,
                'date_emission'   => $data['date_emission'] ?? today(),
                'date_echeance'   => $data['date_echeance'] ?? today()->addDays(15),
                'sous_total'      => $sousTotal,
                'remise_pct'      => $data['remise_pct'] ?? 0,
                'remise_montant'  => $remise + ($data['remise_montant'] ?? 0),
                'total_ttc'       => max(0, $totalTTC),
                'notes'           => $data['notes'] ?? null,
                'statut'          => 'émise',
                'created_by'      => auth('api')->id(),
            ]);

            foreach ($lignes as $ligne) {
                LigneFacture::create([
                    'facture_id'    => $facture->id,
                    'description'   => $ligne['description'],
                    'quantite'      => $ligne['quantite'] ?? 1,
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'total'         => $ligne['total'],
                    'type_ligne'    => $ligne['type_ligne'] ?? 'cours',
                ]);
            }

            return $facture;
        });
    }

    public function enregistrerPaiement(array $data): Paiement
    {
        return DB::transaction(function () use ($data) {
            $facture = Facture::findOrFail($data['facture_id']);

            $paiement = Paiement::create([
                'facture_id'    => $data['facture_id'],
                'montant'       => $data['montant'],
                'mode_paiement' => $data['mode_paiement'],
                'date_paiement' => $data['date_paiement'],
                'notes'         => $data['notes'] ?? null,
                'tenant_id'     => config('tenant.current_id'),
                'eleve_id'      => $facture->eleve_id,
                'recu_par'      => auth('api')->id(),
                'statut'        => 'confirmé',
            ]);

            $totalPaye = $facture->paiements()
                ->where('statut', 'confirmé')->sum('montant');

            $nouveauStatut = match(true) {
                $totalPaye >= $facture->total_ttc => 'payée',
                $totalPaye > 0                   => 'partiellement_payée',
                default                          => 'émise',
            };

            $facture->update(['statut' => $nouveauStatut]);

            return $paiement;
        });
    }

    public function genererFacturePDF(Facture $facture): string
    {
        $facture->load(['eleve.parents', 'lignes', 'paiements']);
        $tenant = app('tenant');

        $pdf = Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'tenant'  => $tenant,
        ])->setPaper('A4', 'portrait');

        $path = "factures/{$facture->tenant_id}/{$facture->numero_facture}.pdf";
        \Storage::disk('public')->put($path, $pdf->output());

        $facture->update(['fichier_url' => $path]);
        return $path;
    }

    public function genererRecuPDF(Paiement $paiement): string
    {
        $paiement->load(['facture.eleve', 'facture.lignes']);
        $tenant = app('tenant');

        $pdf = Pdf::loadView('pdf.recu_paiement', [
            'paiement' => $paiement,
            'tenant'   => $tenant,
        ])->setPaper([0, 0, 595, 420], 'landscape');

        $path = "recus/{$paiement->tenant_id}/{$paiement->id}.pdf";
        \Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    public function getTableauBord(): array
    {
        $tenantId   = config('tenant.current_id');
        $moisActuel = now()->month;
        $annee      = now()->year;

        $caMois = Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $moisActuel)
            ->whereYear('date_paiement',  $annee)
            ->sum('montant');

        $caAnnee = Paiement::where('statut', 'confirmé')
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        $impayes = Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
            ->sum('total_ttc');

        $nbImpayes = Facture::whereIn('statut', ['émise', 'en_retard'])
            ->where('date_echeance', '<', today())
            ->count();

        $caParMois = [];
        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $total = Paiement::where('statut', 'confirmé')
                ->whereMonth('date_paiement', $date->month)
                ->whereYear('date_paiement',  $date->year)
                ->sum('montant');
            $caParMois[] = [
                'mois'  => $date->translatedFormat('M Y'),
                'total' => (float) $total,
            ];
        }

        $modesPayment = Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $moisActuel)
            ->selectRaw('mode_paiement, SUM(montant) as total')
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        return [
            'ca_mois'       => (float) $caMois,
            'ca_annee'      => (float) $caAnnee,
            'impayes'       => (float) $impayes,
            'nb_impayes'    => $nbImpayes,
            'ca_par_mois'   => $caParMois,
            'modes_payment' => $modesPayment,
        ];
    }

    private function genererNumeroFacture(string $tenantId): string
    {
        $annee = now()->year;
        $mois  = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $last  = Facture::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('numero_facture', 'LIKE', "FAC-{$annee}{$mois}-%")
            ->orderByDesc('numero_facture')
            ->value('numero_facture');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;
        return sprintf("FAC-%s%s-%04d", $annee, $mois, $seq);
    }
}
