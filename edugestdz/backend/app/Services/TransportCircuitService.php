<?php

namespace App\Services;

use App\Models\ArretBus;
use App\Models\CircuitTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportCircuitService
{
    /**
     * @return array{circuits: mixed, stats: array{total: int, actifs: int, total_eleves: mixed}}
     */
    public function index(Request $request): array
    {
        $circuits = CircuitTransport::with([
            'chauffeur:id,nom,prenom,telephone',
            'arrets:id,circuit_id,nom,ordre,heure_matin,heure_soir',
        ])
        ->withCount([
            'inscriptionsActives as nb_eleves_actifs',
        ])
        ->when($request->filled('actif'), fn($q) => $q->where('actif', (bool) $request->actif))
        ->orderBy('nom')
        ->get()
        ->map(fn($c) => [
            ...$c->toArray(),
            'nb_eleves'        => $c->nb_eleves_actifs,
            'taux_remplissage' => $c->capacite > 0
                ? round(($c->nb_eleves_actifs / $c->capacite) * 100, 1)
                : 0,
            'alertes'          => $c->alertes_maintenance,
        ]);

        $stats = [
            'total'       => $circuits->count(),
            'actifs'      => $circuits->where('actif', true)->count(),
            'total_eleves'=> $circuits->sum('nb_eleves'),
        ];

        return ['circuits' => $circuits, 'stats' => $stats];
    }

    /**
     * @return array{circuit: CircuitTransport, message: string}
     */
    public function storeCircuit(array $data): array
    {
        $validated = Validator::make($data, [
            'nom'                       => 'required|string|max:100',
            'description'               => 'nullable|string|max:300',
            'chauffeur_id'              => 'nullable|uuid|exists:personnel_non_enseignant,id',
            'vehicule_immat'            => 'nullable|string|max:30',
            'vehicule_marque'           => 'nullable|string|max:50',
            'capacite'                  => 'required|integer|min:1|max:100',
            'tarif_mensuel'             => 'required|numeric|min:0',
            'type_abonnement'           => 'nullable|in:mensuel,trimestriel,annuel',
            'date_controle_technique'   => 'nullable|date',
            'date_expiration_assurance' => 'nullable|date',
            'date_vidange'              => 'nullable|date',
            'note'                      => 'nullable|string|max:500',
        ])->validate();

        $circuit = CircuitTransport::create($validated);

        return [
            'circuit' => $circuit->load('chauffeur:id,nom,prenom'),
            'message' => "Circuit '{$circuit->nom}' cree",
        ];
    }

    /**
     * @return array{circuit: CircuitTransport, nb_eleves: mixed, taux_remplissage: mixed, alertes: mixed, places_restantes: mixed}
     */
    public function showCircuit(string $id): array
    {
        $circuit = CircuitTransport::with([
            'chauffeur:id,nom,prenom,telephone',
            'arrets',
            'inscriptionsActives.eleve:id,nom,prenom,photo_url',
            'inscriptionsActives.arret:id,nom,ordre',
        ])->findOrFail($id);

        return [
            'circuit'          => $circuit,
            'nb_eleves'        => $circuit->nb_eleves_actifs,
            'taux_remplissage' => $circuit->taux_remplissage,
            'alertes'          => $circuit->alertes_maintenance,
            'places_restantes' => $circuit->capacite - $circuit->nb_eleves_actifs,
        ];
    }

    /**
     * @return array{circuit: CircuitTransport}
     */
    public function updateCircuit(string $id, array $data): array
    {
        $circuit   = CircuitTransport::findOrFail($id);
        $validated = Validator::make($data, [
            'nom'                       => 'sometimes|string|max:100',
            'chauffeur_id'              => 'nullable|uuid|exists:personnel_non_enseignant,id',
            'vehicule_immat'            => 'nullable|string|max:30',
            'vehicule_marque'           => 'nullable|string|max:50',
            'capacite'                  => 'sometimes|integer|min:1|max:100',
            'tarif_mensuel'             => 'sometimes|numeric|min:0',
            'actif'                     => 'sometimes|boolean',
            'date_controle_technique'   => 'nullable|date',
            'date_expiration_assurance' => 'nullable|date',
            'date_vidange'              => 'nullable|date',
            'note'                      => 'nullable|string|max:500',
        ])->validate();

        $circuit->update($validated);

        return ['circuit' => $circuit->fresh('chauffeur')];
    }

    /**
     * @return array{success: string}|array{error: string, code: string, status: int}
     */
    public function destroyCircuit(string $id): array
    {
        $circuit = CircuitTransport::findOrFail($id);
        if ($circuit->inscriptionsActives()->exists()) {
            return [
                'error'  => 'Impossible de supprimer : des eleves sont inscrits sur ce circuit',
                'code'   => 'HAS_INSCRIPTIONS',
                'status' => 422,
            ];
        }
        $nom = $circuit->nom;
        $circuit->delete();

        return ['success' => "Circuit '{$nom}' supprime"];
    }

    /**
     * @return array{arrets: mixed, circuit_nom: mixed}
     */
    public function indexArrets(string $circuitId): array
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $arrets  = $circuit->arrets()->withCount(['elevesInscrits as nb_eleves'])->get();

        return ['arrets' => $arrets, 'circuit_nom' => $circuit->nom];
    }

    /**
     * @return array{arret: ArretBus, message: string}
     */
    public function storeArret(string $circuitId, array $data): array
    {
        $circuit   = CircuitTransport::findOrFail($circuitId);
        $validated = Validator::make($data, [
            'nom'         => 'required|string|max:100',
            'adresse'     => 'nullable|string|max:200',
            'wilaya'      => 'nullable|string|max:50',
            'ordre'       => 'required|integer|min:1|max:99',
            'heure_matin' => 'nullable|date_format:H:i',
            'heure_soir'  => 'nullable|date_format:H:i',
        ])->validate();

        $validated['circuit_id'] = $circuit->id;

        $arret = ArretBus::create($validated);

        return [
            'arret'   => $arret,
            'message' => "Arret '{$arret->nom}' ajoute",
        ];
    }

    /**
     * @return array{arret: ArretBus}
     */
    public function updateArret(string $id, array $data): array
    {
        $arret     = ArretBus::findOrFail($id);
        $validated = Validator::make($data, [
            'nom'         => 'sometimes|string|max:100',
            'adresse'     => 'nullable|string|max:200',
            'ordre'       => 'sometimes|integer|min:1',
            'heure_matin' => 'nullable|date_format:H:i',
            'heure_soir'  => 'nullable|date_format:H:i',
            'actif'       => 'sometimes|boolean',
        ])->validate();

        $arret->update($validated);

        return ['arret' => $arret->fresh()];
    }

    /**
     * @return array{success: string}|array{error: string, code: string, status: int}
     */
    public function destroyArret(string $id): array
    {
        $arret = ArretBus::findOrFail($id);
        if ($arret->elevesInscrits()->exists()) {
            return [
                'error'  => 'Des eleves sont affectes a cet arret',
                'code'   => 'HAS_ELEVES',
                'status' => 422,
            ];
        }
        $arret->delete();

        return ['success' => "Arret '{$arret->nom}' supprime"];
    }
}
