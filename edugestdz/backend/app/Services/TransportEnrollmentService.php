<?php

namespace App\Services;

use App\Models\TransportEleve;
use App\Models\Eleve;
use App\Models\CircuitTransport;
use App\Models\ArretBus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des inscriptions élèves sur circuits de transport.
 * Helper `table()` répliquant le scope tenant fail-closed.
 */
class TransportEnrollmentService
{
    public const TABLE_TRANSPORT_ELEVES = 'transport_eleves';

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
     * Vérifier si un élève peut être inscrit sur un circuit.
     */
    public function verifierInscription(string $circuitId, string $eleveId): array
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $eleve = Eleve::findOrFail($eleveId);

        $resultat = ['peut_s_incrire' => true, 'raisons' => []];

        if ($circuit->nb_eleves_actifs >= $circuit->capacite) {
            $resultat['peut_s_incrire'] = false;
            $resultat['raisons'][] = "Circuit complet ({$circuit->capacite} places)";
        }

        if (!$circuit->arrets()->where('id', request()->arret_id ?? null)->exists()) {
            $resultat['peut_s_incrire'] = false;
            $resultat['raisons'][] = "Cet arret n'appartient pas au circuit";
        }

        $dejaInscrit = $this->table('transport_eleves')
            ->where('eleve_id', $eleveId)
            ->where('circuit_id', $circuitId)
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            $resultat['peut_s_incrire'] = false;
            $resultat['raisons'][] = "L'élève est déjà inscrit sur ce circuit";
        }

        return $resultat;
    }

    /**
     * Inscrire un élève sur un circuit.
     */
    public function inscriptionEleve(array $validated): array
    {
        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $eleve = Eleve::findOrFail($validated['eleve_id']);

        $verification = $this->verifierInscription($validated['circuit_id'], $validated['eleve_id']);

        if (!$verification['peut_s_incrire']) {
            return ['error' => $verification['raisons'][0], 'code' => 'VALIDATION_ERROR', 'status' => 422];
        }

        $circuit->nb_eleves_actifs = max(0, $circuit->nb_eleves_actifs); // s'assurer qu'on ne négative pas

        $inscription = TransportEleve::create(array_merge($validated, [
            'tarif_mensuel_applique' => $circuit->tarif_mensuel,
            'actif' => true,
        ]));

        return [
            'inscription' => $inscription->load('arret'),
            'eleve' => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'circuit' => ['nom' => $circuit->nom],
            'tarif_mensuel' => $circuit->tarif_mensuel,
            'success' => "{$eleve->prenom} {$eleve->nom} inscrit sur le circuit '{$circuit->nom}'"
        ];
    }

    /**
     * Désinscription d'un élève.
     */
    public function desinscription(string $id): array
    {
        $inscription = TransportEleve::with(['eleve', 'circuit'])->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);

        return [
            'success' => "{$inscription->eleve->prenom} {$inscription->eleve->nom} desinscrit du circuit '{$inscription->circuit->nom}'"
        ];
    }

    /**
     * CerclesEleve : circuits où un élève est inscrit.
     */
    public function circuitsEleve(string $eleveId): array
    {
        $eleve = Eleve::findOrFail($eleveId);
        $inscriptions = TransportEleve::with([
            'circuit:id,nom,vehicule_marque,vehicule_immat,tarif_mensuel',
            'arret:id,nom,ordre,heure_matin,heure_soir',
        ])
            ->where('eleve_id', $eleveId)
            ->where('actif', true)
            ->get();

        return [
            'eleve' => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'inscriptions' => $inscriptions,
        ];
    }
}