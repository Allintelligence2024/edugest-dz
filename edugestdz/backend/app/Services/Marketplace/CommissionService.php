<?php
namespace App\Services\Marketplace;

use App\Models\{Commission, Reservation, Tenant, Enseignant};
use Illuminate\Support\Facades\DB;

class CommissionService
{
    private const PLAN_RATES = [
        'gratuit' => 0.10,
        'pro' => 0.07,
        'premium' => 0.05,
    ];

    private const DEFAULT_RATE = 0.07;

    public function calculateCommission(float $montant, Tenant $tenant): float
    {
        $plan = $tenant->plan_abonnement ?? 'pro';
        $rate = self::PLAN_RATES[$plan] ?? self::DEFAULT_RATE;

        return round($montant * $rate, 2);
    }

    public function calculateNetEnseignant(float $montant, float $commission): float
    {
        return round($montant - $commission, 2);
    }

    public function persistCommission(Reservation $reservation): Commission
    {
        $tenant = Tenant::find($reservation->tenant_id ?? config('tenant.current_id'));
        $montant = (float) $reservation->montant;

        if ($tenant) {
            $commission = $this->calculateCommission($montant, $tenant);
            $plan = $tenant->plan_abonnement ?? 'pro';
        } else {
            $rate = self::DEFAULT_RATE;
            $commission = round($montant * $rate, 2);
            $plan = 'pro';
        }

        $net = $this->calculateNetEnseignant($montant, $commission);
        $taux = $tenant ? (self::PLAN_RATES[$plan] ?? self::DEFAULT_RATE) : self::DEFAULT_RATE;

        $offre = $reservation->offre;
        $enseignantId = $offre->enseignant_id ?? null;

        return Commission::create([
            'tenant_id'           => $tenant?->id,
            'enseignant_id'       => $enseignantId,
            'reservation_id'      => $reservation->id,
            'montant_total'       => $montant,
            'taux_commission'     => $taux,
            'montant_commission'  => $commission,
            'montant_enseignant'  => $net,
            'statut'              => 'en_attente',
            'plan_tenant'         => $plan,
        ]);
    }

    public function listCommissions(array $filters = [])
    {
        $query = Commission::with(['tenant', 'enseignant', 'reservation.offre']);

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['enseignant_id'])) {
            $query->where('enseignant_id', $filters['enseignant_id']);
        }
        if (!empty($filters['date_debut'])) {
            $query->where('created_at', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->where('created_at', '<=', $filters['date_fin']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 20);
    }

    public function calculatePayout(string $enseignantId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $query = Commission::where('enseignant_id', $enseignantId)
            ->where('statut', 'en_attente');

        if ($dateDebut) {
            $query->where('created_at', '>=', $dateDebut);
        }
        if ($dateFin) {
            $query->where('created_at', '<=', $dateFin);
        }

        $commissions = $query->get();

        return [
            'enseignant_id'     => $enseignantId,
            'nb_commissions'    => $commissions->count(),
            'total_commission'  => round($commissions->sum('montant_commission'), 2),
            'total_a_payer'     => round($commissions->sum('montant_enseignant'), 2),
            'commissions'       => $commissions,
        ];
    }

    public function getStats(?string $tenantId = null): array
    {
        $query = Commission::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = $query->count();
        $enAttente = (clone $query)->where('statut', 'en_attente')->count();
        $payees = (clone $query)->where('statut', 'payee')->count();

        $montants = (clone $query)->selectRaw("
            COALESCE(SUM(montant_commission), 0) as total_commissions,
            COALESCE(SUM(montant_enseignant), 0) as total_enseignants,
            COALESCE(SUM(montant_total), 0) as total_ca
        ")->first();

        return [
            'total_commissions'   => $total,
            'en_attente'          => $enAttente,
            'payees'              => $payees,
            'montant_commissions' => (float) ($montants->total_commissions ?? 0),
            'montant_enseignants' => (float) ($montants->total_enseignants ?? 0),
            'total_ca'            => (float) ($montants->total_ca ?? 0),
        ];
    }
}
