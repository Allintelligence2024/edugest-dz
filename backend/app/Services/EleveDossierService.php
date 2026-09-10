<?php

namespace App\Services;

use App\Http\Requests\Eleve\StoreEleveRequest;
use App\Models\Eleve;
use App\Models\Groupe;
use App\Models\ParentEleve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EleveDossierService
{
    public function __construct(private EleveService $eleves) {}

    /**
     * @return array{eleve: Eleve}
     */
    public function store(StoreEleveRequest $request): array
    {
        $eleve = DB::transaction(function () use ($request) {
            $numero = $this->eleves->genererNumero();

            $eleve = Eleve::create([
                ...$request->validated(),
                'numero_inscription' => $numero,
                'tenant_id'          => config('tenant.current_id'),
            ]);

            if ($request->has('parents')) {
                foreach ($request->parents as $index => $parentData) {
                    $parent = ParentEleve::firstOrCreate(
                        ['telephone_1' => $parentData['telephone_1'], 'tenant_id' => config('tenant.current_id')],
                        [...$parentData, 'tenant_id' => config('tenant.current_id')]
                    );
                    $eleve->parents()->attach($parent->id, ['est_principal' => $index === 0]);
                }
            }

            $this->eleves->genererQRCode($eleve);
            return $eleve;
        });

        cache()->forget("eleves_stats_" . config('tenant.current_id'));

        return ['eleve' => $eleve->load(['wilaya', 'commune', 'parents'])];
    }

    /**
     * @return array{eleve: Eleve}
     */
    public function update(Eleve $eleve, array $validated): array
    {
        $eleve->update($validated);

        cache()->forget("eleves_stats_" . config('tenant.current_id'));

        return ['eleve' => $eleve->fresh(['wilaya', 'commune', 'parents'])];
    }

    /**
     * @return array{error: string}|array{message: string}
     */
    public function destroy(Eleve $eleve): array
    {
        $impayes = $eleve->factures()
            ->whereNotIn('statut', ['payée', 'annulée'])
            ->count();

        if ($impayes > 0) {
            return ['error' => "Impossible de supprimer : {$impayes} facture(s) impayée(s)"];
        }

        $nom = "{$eleve->nom} {$eleve->prenom}";
        $eleve->update(['statut' => 'inactif']);
        $eleve->delete();

        return ['message' => "{$nom} a été archivé"];
    }

    /**
     * @return array{error: string, code: string}|array{inscription: mixed, message: string}
     */
    public function inscrire(Eleve $eleve, array $data): array
    {
        $validated = Validator::make($data, [
            'groupe_id'       => 'required|uuid|exists:groupes,id',
            'annee_scolaire'  => 'nullable|string|regex:/^\\d{4}-\\d{4}$/',
            'date_inscription'=> 'nullable|date',
        ])->validate();

        $groupe = Groupe::findOrFail($validated['groupe_id']);

        $dejaInscrit = $eleve->inscriptions()
            ->where('groupe_id', $groupe->id)
            ->where('statut', 'validée')
            ->exists();

        if ($dejaInscrit) {
            return ['error' => "L'élève est déjà inscrit dans ce groupe", 'code' => 'ALREADY_ENROLLED'];
        }

        $nbInscrits = $groupe->inscriptions()->where('statut', 'validée')->count();
        if ($nbInscrits >= $groupe->capacite_max) {
            return ['error' => "Le groupe est complet ({$groupe->capacite_max} max)", 'code' => 'GROUP_FULL'];
        }

        $inscription = $eleve->inscriptions()->create([
            'tenant_id'       => config('tenant.current_id'),
            'groupe_id'       => $groupe->id,
            'date_inscription'=> $validated['date_inscription'] ?? now()->toDateString(),
            'statut'          => 'validée',
            'inscrit_par'     => auth('api')->id(),
        ]);

        return [
            'inscription' => $inscription->load('groupe'),
            'message'     => "Inscription au groupe {$groupe->nom} validée",
        ];
    }

    /**
     * @return array{photo_url: string}
     */
    public function uploadPhoto(Eleve $eleve, Request $request): array
    {
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        if ($eleve->photo_url) {
            Storage::disk('public')->delete($eleve->photo_url);
        }

        $path = $request->file('photo')->store("photos/eleves/{$eleve->tenant_id}", 'public');
        $eleve->update(['photo_url' => $path]);

        return ['photo_url' => Storage::url($path)];
    }
}
