<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\InscriptionCantine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class InscriptionCantineService
{
    /**
     * @return array{paginator: LengthAwarePaginator, stats: array{total_inscrits: int, par_regime: mixed}}
     */
    public function indexInscriptions(array $data): array
    {
        $validated = Validator::make($data, [
            'regime'   => 'nullable|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'actif'    => 'nullable|boolean',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ])->validate();

        $query = InscriptionCantine::with('eleve:id,nom,prenom,photo_url,niveau_scolaire')
            ->when(isset($validated['actif']), fn($q) => $q->where('actif', $validated['actif']))
            ->when(!empty($validated['regime']), fn($q) => $q->where('regime', $validated['regime']));

        if (!empty($validated['search'])) {
            $query->whereHas('eleve', fn($q) => $q
                ->where('nom', 'like', "%{$validated['search']}%")
                ->orWhere('prenom', 'like', "%{$validated['search']}%")
            );
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_inscrits' => InscriptionCantine::where('actif', true)->count(),
            'par_regime'     => InscriptionCantine::where('actif', true)
                ->selectRaw('regime, COUNT(*) as total')
                ->groupBy('regime')->pluck('total', 'regime'),
        ];

        return ['paginator' => $paginator, 'stats' => $stats];
    }

    /**
     * @return array{inscription: InscriptionCantine, eleve: array{nom: mixed, prenom: mixed}, regime_label: string, message: string}|array{error: string, code: string, status: int}
     */
    public function inscrireEleve(array $data): array
    {
        $validated = Validator::make($data, [
            'eleve_id'        => 'required|uuid|exists:eleves,id',
            'type_abonnement' => 'required|in:mensuel,journalier',
            'regime'          => 'required|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'       => 'nullable|string|max:300',
            'date_debut'      => 'required|date',
            'date_fin'        => 'nullable|date|after:date_debut',
            'tarif_mensuel'   => 'required|numeric|min:0',
            'note'            => 'nullable|string|max:300',
        ])->validate();

        $eleve = Eleve::findOrFail($validated['eleve_id']);

        $dejaInscrit = InscriptionCantine::where('eleve_id', $validated['eleve_id'])
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return [
                'error'  => "{$eleve->prenom} {$eleve->nom} est deja inscrit(e) a la cantine",
                'code'   => 'DEJA_INSCRIT',
                'status' => 409,
            ];
        }

        $inscription = InscriptionCantine::create($validated);

        return [
            'inscription'  => $inscription,
            'eleve'        => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'regime_label' => $inscription->regime_label,
            'message'      => "{$eleve->prenom} {$eleve->nom} inscrit(e) a la cantine ({$inscription->regime_label})",
        ];
    }

    /**
     * @return array{inscription: InscriptionCantine}
     */
    public function updateInscription(string $id, array $data): array
    {
        $inscription = InscriptionCantine::findOrFail($id);
        $validated   = Validator::make($data, [
            'regime'         => 'sometimes|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'      => 'nullable|string|max:300',
            'actif'          => 'sometimes|boolean',
            'tarif_mensuel'  => 'sometimes|numeric|min:0',
            'date_fin'       => 'nullable|date',
            'note'           => 'nullable|string|max:300',
        ])->validate();

        $inscription->update($validated);

        return ['inscription' => $inscription->fresh('eleve')];
    }

    /**
     * @return array{message: string}
     */
    public function desinscrireEleve(string $id): array
    {
        $inscription = InscriptionCantine::with('eleve')->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);

        /** @var Eleve $eleve */
        $eleve = $inscription->eleve;
        $nom = "{$eleve->prenom} {$eleve->nom}";

        return ['message' => "{$nom} desinscrit(e) de la cantine"];
    }
}
