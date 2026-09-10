<?php

namespace App\Services;

use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\TransportEleve;
use Illuminate\Support\Facades\Validator;

class TransportEnrollmentService
{
    /**
     * @return array{peut: true}|array{peut: false, erreur: string, code: string, statut: int}
     */
    public function verifierInscription(CircuitTransport $circuit, Eleve $eleve, string $arretId): array
    {
        if ($circuit->nb_eleves_actifs >= $circuit->capacite) {
            return [
                'peut'   => false,
                'erreur' => "Circuit complet ({$circuit->capacite} places)",
                'code'   => 'CIRCUIT_COMPLET',
                'statut' => 422,
            ];
        }

        if (!$circuit->arrets()->where('id', $arretId)->exists()) {
            return [
                'peut'   => false,
                'erreur' => "Cet arret n'appartient pas au circuit selecctionne",
                'code'   => 'ARRET_INVALIDE',
                'statut' => 422,
            ];
        }

        $dejaInscrit = TransportEleve::where('eleve_id', $eleve->id)
            ->where('circuit_id', $circuit->id)
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return [
                'peut'   => false,
                'erreur' => "{$eleve->prenom} {$eleve->nom} est deja inscrit sur ce circuit",
                'code'   => 'DEJA_INSCRIT',
                'statut' => 409,
            ];
        }

        return ['peut' => true];
    }

    /**
     * @return array{inscription: TransportEleve, eleve: array{nom: mixed, prenom: mixed}, circuit: array{nom: mixed}, tarif_mensuel: mixed, message: string}|array{error: string, code: string, status: int}
     */
    public function inscriptionEleve(array $data): array
    {
        $validated = Validator::make($data, [
            'eleve_id'   => 'required|uuid|exists:eleves,id',
            'circuit_id' => 'required|uuid|exists:circuits_transport,id',
            'arret_id'   => 'required|uuid|exists:arrets_bus,id',
            'abonnement' => 'required|in:aller_retour,aller,retour',
            'date_debut' => 'required|date',
            'date_fin'   => 'nullable|date|after:date_debut',
        ])->validate();

        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $eleve   = Eleve::findOrFail($validated['eleve_id']);

        $verification = $this->verifierInscription($circuit, $eleve, $validated['arret_id']);

        if (!$verification['peut']) {
            return [
                'error'  => $verification['erreur'],
                'code'   => $verification['code'],
                'status' => $verification['statut'],
            ];
        }

        $inscription = TransportEleve::create([
            'eleve_id'               => $validated['eleve_id'],
            'circuit_id'             => $validated['circuit_id'],
            'arret_id'               => $validated['arret_id'],
            'abonnement'             => $validated['abonnement'],
            'date_debut'             => $validated['date_debut'],
            'date_fin'               => $validated['date_fin'] ?? null,
            'tarif_mensuel_applique' => $circuit->tarif_mensuel,
            'actif'                  => true,
        ]);

        return [
            'inscription'   => $inscription->load('arret:id,nom,ordre'),
            'eleve'         => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'circuit'       => ['nom' => $circuit->nom],
            'tarif_mensuel' => $circuit->tarif_mensuel,
            'message'       => "{$eleve->prenom} {$eleve->nom} inscrit sur le circuit '{$circuit->nom}'",
        ];
    }

    /**
     * @return array{message: string}
     */
    public function desinscription(string $id): array
    {
        $inscription = TransportEleve::with(['eleve', 'circuit'])->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);

        /** @var Eleve $eleve */
        $eleve = $inscription->eleve;

        /** @var CircuitTransport $circuit */
        $circuit = $inscription->circuit;

        return [
            'message' => "{$eleve->prenom} {$eleve->nom} desinscrit du circuit '{$circuit->nom}'",
        ];
    }

    /**
     * @return array{eleve: array{id: mixed, nom: mixed, prenom: mixed}, inscriptions: mixed}
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
            'eleve'        => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'inscriptions' => $inscriptions,
        ];
    }
}
