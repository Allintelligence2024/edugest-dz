<?php

namespace App\Services;

use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\PrestatireEntretien;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EntretienPreventifService
{
    /**
     * @return array{entretiens: mixed, alertes_30j: int}
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
     * @return array{entretien: EntretienPreventif, message: string}
     */
    public function planifierPreventif(array $data): array
    {
        $validated = Validator::make($data, [
            'nom'                 => 'required|string|max:150',
            'description'         => 'nullable|string|max:500',
            'local_id'            => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'      => 'nullable|uuid|exists:prestataires_entretien,id',
            'frequence'           => 'required|in:hebdomadaire,mensuel,trimestriel,semestriel,annuel,biennal',
            'prochaine_echeance'  => 'required|date',
            'cout_estime'         => 'nullable|numeric|min:0',
        ])->validate();

        $entretien = EntretienPreventif::create($validated);

        return [
            'entretien' => $entretien->load(['local', 'prestataire']),
            'message'   => "Entretien planifié : {$entretien->nom}",
        ];
    }

    /**
     * @return array{entretien: EntretienPreventif, prochaine_echeance: string, message: string}
     */
    public function realiserPreventif(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'cout_reel'   => 'nullable|numeric|min:0',
            'observations'=> 'nullable|string|max:500',
        ])->validate();

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
            'derniere_realisation'  => today(),
            'prochaine_echeance'    => $prochaine->toDateString(),
        ]);

        /** @var PrestatireEntretien|null $prestataire */
        $prestataire = $entretien->prestataire;

        if (($validated['cout_reel'] ?? 0) > 0) {
            Depense::create([
                'tenant_id'    => config('tenant.current_id'),
                'categorie'    => 'maintenance_reparation',
                'libelle'      => "Entretien préventif : {$entretien->nom}",
                'montant'      => $validated['cout_reel'],
                'date_depense' => today()->toDateString(),
                'mois'         => now()->month,
                'annee'        => now()->year,
                'fournisseur'  => $prestataire?->nom,
                'mode_paiement'=> 'cash',
                'statut'       => 'validee',
                'saisie_par'   => Auth::id(),
            ]);
        }

        return [
            'entretien'         => $entretien->fresh(),
            'prochaine_echeance'=> $prochaine->format('d/m/Y'),
            'message'           => "Entretien réalisé · Prochain : {$prochaine->format('d/m/Y')}",
        ];
    }
}
