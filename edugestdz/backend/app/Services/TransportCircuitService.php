<?php

namespace App\Services;

use App\Models\CircuitTransport;
use App\Models\ArretBus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des circuits de transport scolaire.
 * Helper `table()` répliquant le scope tenant fail-closed.
 */
class TransportCircuitService
{
    public const TABLE_CIRCUITS = 'circuits_transports';
    public const TABLE_ARRETS = 'arrets_bus';

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
     * Liste paginée des circuits avec filtres.
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'actif' => 'nullable|boolean',
        ]);

        $query = $this->table('circuits_transports')->with(['chauffeur:id,nom,prenom,telephone', 'arrets']);

        if (!empty($validated['actif'])) {
            $query->where('actif', $validated['actif']);
        }

        $circuits = $query->orderBy('nom')->get();

        $data = $circuits->map(fn($c) => [
            'id'            => $c->id,
            'nom'           => $c->nom,
            'description'   => $c->description,
            'chauffeur'     => $c->chauffeur ? $c->chauffeur->only(['nom', 'prenom', 'telephone']) : null,
            'nb_eleves_actifs' => $c->nb_eleves_actifs,
            'taux_remplissage' => $c->capacite > 0
                ? round(($c->nb_eleves_actifs / $c->capacite) * 100, 1)
                : 0,
            'alertes'       => $c->alertes_maintenance,
        ]);

        $stats = [
            'total'       => $data->count(),
            'actifs'      => $data->where('actif', true)->count(),
            'total_eleves'=> $data->sum('nb_eleves_actifs'),
        ];

        return ['circuits' => $data, 'stats' => $stats];
    }

    /**
     * Création d'un circuit.
     */
    public function storeCircuit(array $validated): array
    {
        $circuit = CircuitTransport::create($validated);

        return ['circuit' => $circuit->load('chauffeur'), 'success' => "Circuit '{$circuit->nom}' créé"];
    }

    /**
     * Récupération d'un circuit avec détails.
     */
    public function showCircuit(string $id): array
    {
        $circuit = CircuitTransport::with(['chauffeur', 'arrets', 'inscriptionsActives.eleve:id,nom,prenom,photo_url'])->findOrFail($id);

        return [
            'circuit'          => $circuit,
            'nb_eleves'        => $circuit->nb_eleves_actifs,
            'taux_remplissage' => $circuit->taux_remplissage,
            'alertes'          => $circuit->alertes_maintenance,
            'places_restantes' => $circuit->capacite - $circuit->nb_eleves_actifs,
        ];
    }

    /**
     * Mise à jour d'un circuit.
     */
    public function updateCircuit(string $id, array $validated): array
    {
        $circuit = CircuitTransport::findOrFail($id);
        $circuit->update($validated);

        return ['circuit' => $circuit->fresh('chauffeur'), 'success' => 'Circuit mis à jour'];
    }

    /**
     * Suppression d'un circuit.
     */
    public function destroyCircuit(string $id): array
    {
        $circuit = CircuitTransport::findOrFail($id);

        if ($circuit->inscriptionsActives()->exists()) {
            return ['error' => 'Impossible de supprimer : des élèves sont inscrits sur ce circuit', 'code' => 'HAS_INSCRIPTIONS', 'status' => 422];
        }

        $nom = $circuit->nom;
        $circuit->delete();

        return ['success' => "Circuit '{$nom}' supprimé"];
    }

    /**
     * Liste des arrêts d'un circuit.
     */
    public function indexArrets(string $circuitId): array
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $arrets = $circuit->arrets()->withCount(['elevesInscrits as nb_eleves'])->get();

        return ['arrets' => $arrets, 'circuit_nom' => $circuit->nom];
    }

    /**
     * Création d'un arrêt.
     */
    public function storeArret(string $circuitId, array $validated): array
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $validated['circuit_id'] = $circuit->id;

        $arret = ArretBus::create($validated);

        return ['arret' => $arret, 'success' => "Arret '{$arret->nom}' ajouté"];
    }

    /**
     * Mise à jour d'un arrêt.
     */
    public function updateArret(string $id, array $validated): array
    {
        $arret = ArretBus::findOrFail($id);
        $arret->update($validated);

        return ['arret' => $arret->fresh(), 'success' => 'Arrêt mis à jour'];
    }

    /**
     * Suppression d'un arrêt.
     */
    public function destroyArret(string $id): array
    {
        $arret = ArretBus::findOrFail($id);

        if ($arret->elevesInscrits()->exists()) {
            return ['error' => 'Des élèves sont affectés sur cet arrêt', 'code' => 'HAS_ELEVES', 'status' => 422];
        }

        $nom = $arret->nom;
        $arret->delete();

        return ['success' => "Arrêt '{$nom}' supprimé"];
    }
}