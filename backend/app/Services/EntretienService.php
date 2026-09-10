<?php

namespace App\Services;

use App\Models\Depense;
use App\Models\InterventionEntretien;
use App\Models\PrestatireEntretien;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EntretienService
{
    /**
     * @return array{paginator: LengthAwarePaginator, stats: array{total_ouverts: int, urgentes: int, hautes: int, resolues_mois: int}}
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

        $paginator = InterventionEntretien::with([
            'local:id,nom,type',
            'prestataire:id,nom,specialite',
        ])
            ->when($validated['statut']   ?? null, fn($q, $s) => $q->where('statut', $s))
            ->when($validated['priorite'] ?? null, fn($q, $p) => $q->where('priorite', $p))
            ->when($validated['type']     ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($validated['local_id'] ?? null, fn($q, $l) => $q->where('local_id', $l))
            ->orderByRaw("CASE priorite
                WHEN 'urgente' THEN 1
                WHEN 'haute'   THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse'   THEN 4
                ELSE 5 END")
            ->orderByDesc('date_signalement')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_ouverts' => InterventionEntretien::ouverts()->count(),
            'urgentes'      => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'hautes'        => InterventionEntretien::ouverts()->priorite('haute')->count(),
            'resolues_mois' => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', now()->month)->count(),
        ];

        return ['paginator' => $paginator, 'stats' => $stats];
    }

    /**
     * @return array{intervention: InterventionEntretien, priorite_label: mixed, message: string}
     */
    public function signalerIntervention(array $data): array
    {
        $validated = Validator::make($data, [
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:panne,degradation,entretien_preventif,renovation,nettoyage,inspection',
            'priorite'        => 'required|in:urgente,haute,normale,basse',
            'local_id'        => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'  => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_signalement'=> 'nullable|date',
            'cout_estime'     => 'nullable|numeric|min:0',
        ])->validate();

        $validated['date_signalement'] = $validated['date_signalement'] ?? today()->toDateString();
        $validated['signale_par']      = Auth::id();
        $validated['statut']           = 'signale';

        $intervention = InterventionEntretien::create($validated);

        return [
            'intervention'   => $intervention->load(['local', 'prestataire']),
            'priorite_label' => $intervention->priorite_label,
            'message'        => "Intervention signalée : {$intervention->titre}",
        ];
    }

    /**
     * @return array{intervention: InterventionEntretien, message: string}|array{error: string, code: string, status: int}
     */
    public function changerStatut(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'statut'                  => 'required|in:en_cours,en_attente,annule',
            'prestataire_id'          => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_debut_intervention' => 'nullable|date',
        ])->validate();

        $intervention = InterventionEntretien::findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return [
                'error'  => 'Cette intervention est déjà résolue',
                'code'   => 'DEJA_RESOLU',
                'status' => 409,
            ];
        }

        $updateData = ['statut' => $validated['statut']];

        if ($validated['statut'] === 'en_cours') {
            $updateData['date_debut_intervention'] = $validated['date_debut_intervention'] ?? today()->toDateString();
            if (isset($validated['prestataire_id'])) {
                $updateData['prestataire_id'] = $validated['prestataire_id'];
            }
        }

        $intervention->update($updateData);

        return [
            'intervention' => $intervention->fresh(['local', 'prestataire']),
            'message'      => "Statut mis à jour : {$intervention->statut_label}",
        ];
    }

    /**
     * @return array{intervention: InterventionEntretien, depense_creee: bool, message: string}|array{error: string, code: string, status: int}
     */
    public function resoudreIntervention(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'cout_reel'            => 'required|numeric|min:0',
            'rapport_intervention' => 'nullable|string|max:2000',
            'date_resolution'      => 'nullable|date',
            'date_entretien_suivant'=> 'nullable|date|after:today',
            'etat_local_apres'     => 'nullable|in:bon,moyen,mauvais,critique',
        ])->validate();

        $intervention = InterventionEntretien::with('local')->findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return [
                'error'  => 'Déjà résolu',
                'code'   => 'DEJA_RESOLU',
                'status' => 409,
            ];
        }

        /** @var PrestatireEntretien|null $prestataire */
        $prestataire = $intervention->prestataire;

        DB::transaction(function () use ($intervention, $validated, $prestataire) {
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
                    'fournisseur'  => $prestataire?->nom,
                    'mode_paiement'=> 'cash',
                    'statut'       => 'validee',
                    'saisie_par'   => Auth::id(),
                    'note'         => "Lié à l'intervention #{$intervention->id}",
                ]);

                $intervention->update(['depense_id' => $depense->id]);
            }
        });

        return [
            'intervention' => $intervention->fresh(['local', 'prestataire', 'depense']),
            'depense_creee'=> $validated['cout_reel'] > 0,
            'message'      => "Intervention résolue — Coût : " . number_format($validated['cout_reel'], 2) . " DA",
        ];
    }
}
