<?php

namespace App\Services;

use App\Models\EntretienPreventif;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des entraînements préventifs.
 * Helper `table()` répliquant le scope tenant fail-closed.
 */
class EntretienPreventifService
{
    public const TABLE_ENTRENANTS = 'entretiens_preventifs';

    private function table(string $table): Builder
    {
        $builder = DB::table($table);
        $tenantId = config('tenant.current_id');

        if ($tenantId !== null) {
            $builder->where('tenant_id', $tenantId);
        } else {
            $builder->whereRaw('1 = 0');
        }

        return $builder;
    }

    /**
     * Liste des entraînements préventifs actifs avec retard et délais.
     */
    public function index(): array
    {
        $entretiens = EntretienPreventif::where('actif', true)
            ->with(['local:id,nom', 'prestataire:id,nom'])
            ->orderBy('prochaine_echeance')
            ->get()
            ->map(fn($e) => array_merge($e->toArray(), [
                'en_retard'              => $e->en_retard,
                'jours_avant_echeance'   => $e->jours_avant_echeance,
            ]));

        $alertes = $entretiens->filter(fn($e) => $e['jours_avant_echeance'] <= 30)->count();

        return ['entretiens' => $entretiens, 'alertes_30j' => $alertes];
    }

    /**
     * Planification d'un nouvel entretien préventif.
     */
    public function planifierPreventif(array $validated): array
    {
        $entretien = EntretienPreventif::create($validated);

        return ['entretien' => $entretien->load(['local', 'prestataire']), 'success' => "Entretien planifié : {$entretien->nom}"];
    }

    /**
     * Réalisation d'un entretien préventif.
     */
    public function realizierPreventif(string $id, array $validated): array
    {
        $validated = array_merge($validated, ['cout_reel' => $validated['cout_reel'] ?? 0]);

        $entretien = EntretienPreventif::findOrFail($id);

        $prochaine = match ($entretien->frequence) {
            'hebdomadaire' => now()->addWeek(),
            'mensuel'      => now()->addMonth(),
            'trimestriel'  => now()->addMonths(3),
            'semestriel'   => now()->addMonths(6),
            'annuel'       => now()->addYear(),
            'biennal'      => now()->addYears(2),
            default        => now()->addYear(),
        };

        $entretien->update([
            'derniere_realisation' => today(),
            'prochaine_echeance'   => $prochaine->toDateString(),
        ]);

        if (($validated['cout_reel'] ?? 0) > 0) {
            Depense::create([
                'tenant_id'    => config('tenant.current_id'),
                'categorie'    => 'maintenance_reparation',
                'libelle'      => "Entretien préventif : {$entretien->nom}",
                'montant'      => $validated['cout_reel'],
                'date_depense' => today()->toDateString(),
                'mois'         => now()->month,
                'annee'        => now()->year,
                'fournisseur'  => $entretien->prestataire?->nom,
                'mode_paiement'=> 'cash',
                'statut'       => 'validee',
                'saisie_par'   => Auth::id(),
            ]);
        }

        return ['entretien' => $entretien->fresh(), 'prochaine_echeance' => $prochaine->format('d/m/Y'), 'success' => "Entretien réalisé · Prochain : {$prochaine->format('d/m/Y')}"];
    }
}