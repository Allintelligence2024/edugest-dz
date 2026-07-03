<?php

namespace App\Services;

use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\ReservationMarketplace;
use App\Models\AvisMarketplace;
use Illuminate\Support\Facades\Cache;

class MarketplaceService
{
    public function rechercher(array $filtres): \Illuminate\Support\Collection
    {
        $cacheKey = 'marketplace_search_' . md5(serialize($filtres));

        return Cache::remember($cacheKey, 300, function () use ($filtres) {
            $query = ProfilMarketplace::visible()
                ->with(['offres' => fn($q) => $q->active()->select('id','tenant_id','matiere','tarif_heure','type','essai_gratuit')]);

            if (! empty($filtres['wilaya'])) {
                $query->wilaya($filtres['wilaya']);
            }
            if (! empty($filtres['matiere'])) {
                $query->matiere($filtres['matiere']);
            }
            if (! empty($filtres['niveau'])) {
                $query->niveau($filtres['niveau']);
            }
            if (! empty($filtres['tarif_max'])) {
                $query->where('tarif_heure_min', '<=', $filtres['tarif_max']);
            }
            if (! empty($filtres['essai_gratuit'])) {
                $query->where('accepte_essai_gratuit', true);
            }
            if (! empty($filtres['verifie'])) {
                $query->where('verifie', true);
            }

            return $query->get()
                ->map(fn($profil) => $this->scorerProfil($profil, $filtres))
                ->sortByDesc('score')
                ->values();
        });
    }

    private function scorerProfil(ProfilMarketplace $profil, array $filtres): array
    {
        $score = 0;

        $score += (float) $profil->note_moyenne * 10;

        if ($profil->verifie) $score += 20;

        if ($profil->accepte_essai_gratuit) $score += 15;

        $score += min($profil->nb_avis, 10);

        $score += min($profil->annees_experience, 5);

        return array_merge($profil->toArray(), ['score' => round($score, 1)]);
    }

    public function creerReservation(array $data): ReservationMarketplace
    {
        $offre = OffreCours::findOrFail($data['offre_id']);

        $duree   = $data['duree_minutes'] ?? $offre->duree_seance;
        $montant = $offre->essai_gratuit && ($data['type'] ?? '') === 'essai'
            ? 0
            : round($offre->tarif_heure * ($duree / 60), 2);

        $reservation = ReservationMarketplace::create([
            'offre_id'        => $offre->id,
            'parent_id'       => $data['parent_id'],
            'eleve_id'        => $data['eleve_id'],
            'tenant_id'       => $offre->tenant_id,
            'date_souhaitee'  => $data['date_souhaitee'],
            'duree_minutes'   => $duree,
            'type'            => $data['type'] ?? 'cours_unique',
            'statut'          => 'en_attente',
            'montant'         => $montant,
            'statut_paiement' => $montant == 0 ? 'gratuit' : 'en_attente',
            'message_parent'  => $data['message_parent'] ?? null,
        ]);

        Cache::tags(['marketplace'])->flush();

        return $reservation->load('offre');
    }

    public function confirmerReservation(ReservationMarketplace $reservation, string $reponse = null): ReservationMarketplace
    {
        $reservation->update([
            'statut'         => 'confirmee',
            'reponse_centre' => $reponse,
            'confirme_le'    => now(),
        ]);

        return $reservation;
    }

    public function annulerReservation(ReservationMarketplace $reservation, string $par, string $motif = null): ReservationMarketplace
    {
        if (! $reservation->peutEtreAnnule()) {
            throw new \RuntimeException('Cette réservation ne peut plus être annulée.');
        }

        $reservation->update([
            'statut'          => $par === 'parent' ? 'annulee_parent' : 'annulee_centre',
            'reponse_centre'  => $motif,
        ]);

        return $reservation;
    }

    public function getStats(): array
    {
        return Cache::remember('marketplace_stats', 600, fn() => [
            'profils_actifs'      => ProfilMarketplace::where('visible', true)->count(),
            'profils_verifies'    => ProfilMarketplace::where('verifie', true)->count(),
            'total_reservations'  => ReservationMarketplace::count(),
            'reservations_mois'   => ReservationMarketplace::whereMonth('created_at', now()->month)->count(),
            'taux_confirmation'   => $this->tauxConfirmation(),
            'note_moyenne_globale'=> round((float) ProfilMarketplace::avg('note_moyenne'), 2),
            'top_wilayas'         => ProfilMarketplace::visible()
                ->selectRaw('wilaya, COUNT(*) as total')
                ->groupBy('wilaya')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'wilaya'),
        ]);
    }

    private function tauxConfirmation(): float
    {
        $total     = ReservationMarketplace::whereNotIn('statut', ['en_attente'])->count();
        $confirmes = ReservationMarketplace::where('statut', 'confirmee')->count();
        return $total > 0 ? round(($confirmes / $total) * 100, 1) : 0;
    }
}
