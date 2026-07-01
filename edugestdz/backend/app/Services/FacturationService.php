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
                    'tenant_id'     => $tenantId,
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
        $moisActuel = now()->month;
        $annee      = now()->year;
        $tenantId   = config('tenant.current_id');

        $paiementsStats = Paiement::where('statut', 'confirmé')
            ->whereYear('date_paiement', $annee)
            ->selectRaw("
                SUM(montant) as ca_annee,
                SUM(CASE WHEN EXTRACT(MONTH FROM date_paiement) = ? THEN montant ELSE 0 END) as ca_mois
            ", [$moisActuel])
            ->first();

        $impayesStats = Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
            ->selectRaw("
                SUM(total_ttc) as total_impayes,
                COUNT(CASE WHEN date_echeance < CURRENT_DATE THEN 1 END) as nb_impayes
            ")
            ->first();

        $caParMois = Paiement::where('statut', 'confirmé')
            ->where('date_paiement', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("
                DATE_TRUNC('month', date_paiement) as mois_date,
                SUM(montant) as total
            ")
            ->groupByRaw("DATE_TRUNC('month', date_paiement)")
            ->orderBy('mois_date')
            ->get()
            ->map(fn($r) => [
                'mois'  => \Carbon\Carbon::parse($r->mois_date)->translatedFormat('M Y'),
                'total' => (float) $r->total,
            ]);

        $modesPayment = Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $moisActuel)
            ->whereYear('date_paiement', $annee)
            ->selectRaw('mode_paiement, SUM(montant) as total')
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        return [
            'ca_mois'      => (float) ($paiementsStats->ca_mois ?? 0),
            'ca_annee'     => (float) ($paiementsStats->ca_annee ?? 0),
            'impayes'      => (float) ($impayesStats->total_impayes ?? 0),
            'nb_impayes'   => (int)   ($impayesStats->nb_impayes ?? 0),
            'ca_par_mois'  => $caParMois,
            'modes_payment'=> $modesPayment,
        ];
    }

    /**
     * Génère la facture mensuelle complète d'un élève :
     * scolarité + transport (si inscrit) + cantine (si inscrit).
     * Évite les doublons : ne génère pas si déjà facturé pour ce mois.
     */
    public function genererFactureMensuelleEleve(
        string $eleveId,
        int $mois,
        int $annee,
        float $tarifScolarite = 0
    ): ?Facture {
        $eleve     = \App\Models\Eleve::findOrFail($eleveId);
        $tenantId  = config('tenant.current_id');

        // ── Vérifier qu'une facture n'existe pas déjà ce mois ──
        $dejaFacturee = Facture::where('eleve_id', $eleveId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->exists();

        if ($dejaFacturee) {
            return null; // idempotent — ne pas doubler
        }

        $lignes = [];

        // ── Ligne scolarité (si tarif > 0) ──
        if ($tarifScolarite > 0) {
            $lignes[] = [
                'description'   => "Frais de scolarité — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                'quantite'      => 1,
                'prix_unitaire' => $tarifScolarite,
                'total'         => $tarifScolarite,
                'type_ligne'    => 'cours',
            ];
        }

        // ── Ligne transport (si inscrit et actif) ──
        $transport = \App\Models\TransportEleve::where('eleve_id', $eleveId)
            ->where('actif', true)
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()))
            ->with('circuit:id,nom')
            ->first();

        if ($transport && $transport->tarif_mensuel_applique > 0) {
            $lignes[] = [
                'description'   => "Transport scolaire — {$transport->circuit->nom} — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                'quantite'      => 1,
                'prix_unitaire' => $transport->tarif_mensuel_applique,
                'total'         => $transport->tarif_mensuel_applique,
                'type_ligne'    => 'transport',
            ];
        }

        // ── Ligne cantine (si inscrit et actif) ──
        $cantine = \App\Models\InscriptionCantine::where('eleve_id', $eleveId)
            ->where('actif', true)
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()))
            ->first();

        if ($cantine && $cantine->tarif_mensuel > 0) {
            // Pour abonnement journalier : compter les repas réels du mois
            if ($cantine->type_abonnement === 'journalier') {
                $nbRepas = \App\Models\RepasJournalier::where('eleve_id', $eleveId)
                    ->where('present', true)
                    ->whereMonth('date_repas', $mois)
                    ->whereYear('date_repas', $annee)
                    ->count();

                if ($nbRepas > 0) {
                    $prixUnitaire = $cantine->tarif_mensuel; // ici = prix par repas
                    $total        = $nbRepas * $prixUnitaire;
                    $lignes[]     = [
                        'description'   => "Cantine ({$nbRepas} repas) — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                        'quantite'      => $nbRepas,
                        'prix_unitaire' => $prixUnitaire,
                        'total'         => $total,
                        'type_ligne'    => 'cantine',
                    ];
                }
            } else {
                // Forfait mensuel
                $lignes[] = [
                    'description'   => "Cantine (forfait mensuel) — " . \Carbon\Carbon::create($annee, $mois)->translatedFormat('F Y'),
                    'quantite'      => 1,
                    'prix_unitaire' => $cantine->tarif_mensuel,
                    'total'         => $cantine->tarif_mensuel,
                    'type_ligne'    => 'cantine',
                ];
            }
        }

        // ── Si aucune ligne → pas de facture ──
        if (empty($lignes)) {
            return null;
        }

        // ── Créer la facture via la méthode existante ──
        return $this->creerFacture([
            'eleve_id'      => $eleveId,
            'mois'          => $mois,
            'annee'         => $annee,
            'date_emission' => today()->toDateString(),
            'date_echeance' => today()->addDays(15)->toDateString(),
            'lignes'        => $lignes,
            'notes'         => "Facture mensuelle auto-générée",
        ]);
    }

    /**
     * Génère les factures mensuelles de TOUS les élèves actifs du tenant.
     * Utilisé par la commande Artisan mensuelle.
     */
    public function genererFacturesMensuelles(int $mois, int $annee, float $tarifScolariteDefaut = 0): array
    {
        $eleves  = \App\Models\Eleve::actifs()->get();
        $resultats = ['generees' => 0, 'ignorees' => 0, 'erreurs' => []];

        foreach ($eleves as $eleve) {
            try {
                $facture = $this->genererFactureMensuelleEleve(
                    $eleve->id,
                    $mois,
                    $annee,
                    $tarifScolariteDefaut
                );

                if ($facture) {
                    $resultats['generees']++;
                } else {
                    $resultats['ignorees']++; // déjà facturé ou aucune ligne
                }
            } catch (\Throwable $e) {
                $resultats['erreurs'][] = [
                    'eleve'  => $eleve->nom_complet,
                    'erreur' => $e->getMessage(),
                ];
            }
        }

        return $resultats;
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
