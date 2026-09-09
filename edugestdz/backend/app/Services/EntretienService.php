<?php

namespace App\Services;

use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des interventions d'entretien bâtiment.
 * Centralise la création de dépenses de maintenance et le traitement
 * des interventions préventives, en répliquant le scope tenant fail-closed.
 */
class EntretienService
{
    public const TABLE_DEPENSES = 'depenses';
    public const TABLE_INTERVENTIONS = 'interventions_entretiens';
    public const TABLE_PREVENTIFS = 'entretiens_preventifs';

    /**
     * @var Builder
     */
    private $builder;

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
     * Liste paginée des interventions avec filtres.
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'statut'   => 'nullable|in:signale,en_cours,en_attente,resolu,annule',
            'priorite' => 'nullable|in:urgente,haute,normale,basse',
            'type'     => 'nullable|string',
            'local_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = InterventionEntretien::with(['local:id,nom,type', 'prestataire:id,nom,specialite']);

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }
        if (!empty($validated['priorite'])) {
            $query->where('priorite', $validated['priorite']);
        }
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (!empty($validated['local_id'])) {
            $query->where('local_id', $validated['local_id']);
        }

        $paginator = $query->when(
            $validated['priorite'] ?? null,
            fn($q, $p) => $q->where('priorite', $p)
        )->when(
            $validated['type'] ?? null,
            fn($q, $t) => $q->where('type', $t)
        )->when(
            $validated['local_id'] ?? null,
            fn($q, $l) => $q->where('local_id', $l)
        )->orderByRaw("CASE priorite WHEN 'urgente' THEN 1 WHEN 'haute' THEN 2 WHEN 'normale' THEN 3 ELSE 4 END")
        ->orderByDesc('date_signalement')
        ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_ouverts' => $this->table('interventions_entretiens')->ouverts()->count(),
            'urgentes'      => $this->table('interventions_entretiens')->ouverts()->priorite('urgente')->count(),
            'hautes'        => $this->table('interventions_entretiens')->ouverts()->priorite('haute')->count(),
            'resolus_mois'    => $this->table('interventions_entretiens')
                ->ouverts()->whereMonth('date_resolution', now()->month)->count(),
        ];

        return ['paginator' => $paginator, 'stats' => $stats];
    }

    /**
     * Signaler une nouvelle intervention.
     */
    public function signalerIntervention(array $validated): array
    {
        $validated['date_signalement'] = $validated['date_signalement'] ?? today()->toDateString();
        $validated['signale_par']      = Auth::id();
        $validated['statut']           = 'signale';

        $intervention = InterventionEntretien::create($validated);

        return ['intervention' => $intervention->load(['local', 'prestataire']), 'success' => "Intervention signalée : {$intervention->titre}"];
    }

    /**
     * Changer le statut d'une intervention.
     */
    public function changerStatut(string $id, array $validated): array
    {
        $validated = array_merge($validated, [
            'statut' => $validated['statut'] ?? null,
        ]);

        $intervention = InterventionEntretien::findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return ['error' => 'Cette intervention est déjà résolue', 'code' => 'DEJA_RESOLU', 'status' => 409];
        }

        $data = ['statut' => $validated['statut']];

        if ($validated['statut'] === 'en_cours') {
            $data['date_debut_intervention'] = $validated['date_debut_intervention'] ?? today()->toDateString();
            if (isset($validated['prestataire_id'])) {
                $data['prestataire_id'] = $validated['prestataire_id'];
            }
        }

        $intervention->update($data);

        return ['intervention' => $intervention->fresh(['local', 'prestataire']), 'success' => "Statut mis à jour : {$intervention->statut_label}"];
    }

    /**
     * Résoudre une intervention (mise à jour du statut, dépôt de la dépense).
     */
    public function resoudreIntervention(string $id, array $validated): array
    {
        $validated = array_merge($validated, [
            'cout_reel' => $validated['cout_reel'] ?? 0,
        ]);

        $intervention = InterventionEntretien::with('local')->findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return ['error' => 'Déjà résolu', 'code' => 'DEJA_RESOLU', 'status' => 409];
        }

        return DB::transaction(function () use ($intervention, $validated) {
            $intervention->update([
                'statut'                => 'resolu',
                'cout_reel'             => $validated['cout_reel'],
                'rapport_intervention'  => $validated['rapport_intervention'] ?? null,
                'date_resolution'       => $validated['date_resolution'] ?? today()->toDateString(),
                'date_entretien_suivant'=> $validated['date_entretien_suivant'] ?? null,
            ]);

            if (isset($validated['etat_local_apres']) && $intervention->local) {
                $intervention->local->update(['etat_general' => $validated['etat_local_apres']]);
            }

            if ($validated['cout_reel'] > 0) {
                $depense = Depense::create([
                    'tenant_id'    => config('tenant.current_id'),
                    'categorie'    => 'maintenance_reparation',
                    'libelle'      => "Entretien : {$intervention->titre}",
                    'montant'      => $validated['cout_reel'],
                    'date_depense' => today()->toDateString(),
                    'mois'         => now()->month,
                    'annee'        => now()->year,
                    'fournisseur'  => $intervention->prestataire?->nom,
                    'mode_paiement'=> 'cash',
                    'statut'       => 'validee',
                    'saisie_par'   => auth()->id(),
                    'note'         => "Lié à l'intervention #{$intervention->id}",
                ]);

                $intervention->update(['depense_id' => $depense->id]);
            }

            return ['intervention' => $intervention->fresh(['local', 'prestataire', 'depense']), 'depense_creee' => $validated['cout_reel'] > 0];
        });
    }
}