<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ArretBus;
use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\PointageBus;
use App\Models\TransportEleve;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransportController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * @OA\Get(
     *     path="/api/v1/transport/circuits",
     *     summary="Liste des circuits de transport",
     *     tags={"Transport"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="actif", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Circuits avec stats",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="circuits", type="array", @OA\Items(ref="#/components/schemas/CircuitTransport")),
     *                 @OA\Property(property="stats",    type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function indexCircuits(Request $request): JsonResponse
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

        return $this->success([
            'circuits' => $circuits,
            'stats'    => [
                'total'       => $circuits->count(),
                'actifs'      => $circuits->where('actif', true)->count(),
                'total_eleves'=> $circuits->sum('nb_eleves'),
            ],
        ], 'Circuits recuperes');
    }

    public function storeCircuit(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        $circuit = CircuitTransport::create($validated);

        return $this->created(
            $circuit->load('chauffeur:id,nom,prenom'),
            "Circuit '{$circuit->nom}' cree"
        );
    }

    public function showCircuit(string $id): JsonResponse
    {
        $circuit = CircuitTransport::with([
            'chauffeur:id,nom,prenom,telephone',
            'arrets',
            'inscriptionsActives.eleve:id,nom,prenom,photo_url',
            'inscriptionsActives.arret:id,nom,ordre',
        ])->findOrFail($id);

        return $this->success([
            'circuit'          => $circuit,
            'nb_eleves'        => $circuit->nb_eleves_actifs,
            'taux_remplissage' => $circuit->taux_remplissage,
            'alertes'          => $circuit->alertes_maintenance,
            'places_restantes' => $circuit->capacite - $circuit->nb_eleves_actifs,
        ]);
    }

    public function updateCircuit(Request $request, string $id): JsonResponse
    {
        $circuit   = CircuitTransport::findOrFail($id);
        $validated = $request->validate([
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
        ]);

        $circuit->update($validated);
        return $this->success($circuit->fresh('chauffeur'), 'Circuit mis a jour');
    }

    public function destroyCircuit(string $id): JsonResponse
    {
        $circuit = CircuitTransport::findOrFail($id);
        if ($circuit->inscriptionsActives()->exists()) {
            return $this->error(
                'Impossible de supprimer : des eleves sont inscrits sur ce circuit',
                'HAS_INSCRIPTIONS', 422
            );
        }
        $nom = $circuit->nom;
        $circuit->delete();
        return $this->success(null, "Circuit '{$nom}' supprime");
    }

    public function indexArrets(string $circuitId): JsonResponse
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $arrets  = $circuit->arrets()->withCount(['elevesInscrits as nb_eleves'])->get();

        return $this->success($arrets, "Arrets du circuit '{$circuit->nom}'");
    }

    public function storeArret(Request $request, string $circuitId): JsonResponse
    {
        $circuit   = CircuitTransport::findOrFail($circuitId);
        $validated = $request->validate([
            'nom'         => 'required|string|max:100',
            'adresse'     => 'nullable|string|max:200',
            'wilaya'      => 'nullable|string|max:50',
            'ordre'       => 'required|integer|min:1|max:99',
            'heure_matin' => 'nullable|date_format:H:i',
            'heure_soir'  => 'nullable|date_format:H:i',
        ]);

        $validated['circuit_id'] = $circuit->id;

        $arret = ArretBus::create($validated);
        return $this->created($arret, "Arret '{$arret->nom}' ajoute");
    }

    public function updateArret(Request $request, string $id): JsonResponse
    {
        $arret     = ArretBus::findOrFail($id);
        $validated = $request->validate([
            'nom'         => 'sometimes|string|max:100',
            'adresse'     => 'nullable|string|max:200',
            'ordre'       => 'sometimes|integer|min:1',
            'heure_matin' => 'nullable|date_format:H:i',
            'heure_soir'  => 'nullable|date_format:H:i',
            'actif'       => 'sometimes|boolean',
        ]);
        $arret->update($validated);
        return $this->success($arret->fresh(), 'Arret mis a jour');
    }

    public function destroyArret(string $id): JsonResponse
    {
        $arret = ArretBus::findOrFail($id);
        if ($arret->elevesInscrits()->exists()) {
            return $this->error('Des eleves sont affectes a cet arret', 'HAS_ELEVES', 422);
        }
        $arret->delete();
        return $this->success(null, "Arret '{$arret->nom}' supprime");
    }

    public function inscrireEleve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'   => 'required|uuid|exists:eleves,id',
            'circuit_id' => 'required|uuid|exists:circuits_transport,id',
            'arret_id'   => 'required|uuid|exists:arrets_bus,id',
            'abonnement' => 'required|in:aller_retour,aller,retour',
            'date_debut' => 'required|date',
            'date_fin'   => 'nullable|date|after:date_debut',
        ]);

        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $eleve   = Eleve::findOrFail($validated['eleve_id']);

        if ($circuit->nb_eleves_actifs >= $circuit->capacite) {
            return $this->error("Circuit complet ({$circuit->capacite} places)", 'CIRCUIT_COMPLET', 422);
        }

        if (!$circuit->arrets()->where('id', $validated['arret_id'])->exists()) {
            return $this->error("Cet arret n'appartient pas au circuit selecctionne", 'ARRET_INVALIDE', 422);
        }

        $dejaInscrit = TransportEleve::where('eleve_id', $validated['eleve_id'])
            ->where('circuit_id', $validated['circuit_id'])
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return $this->error("{$eleve->prenom} {$eleve->nom} est deja inscrit sur ce circuit", 'DEJA_INSCRIT', 409);
        }

        $inscription = TransportEleve::create(array_merge($validated, [
            'tarif_mensuel_applique' => $circuit->tarif_mensuel,
            'actif'                  => true,
        ]));

        return $this->created([
            'inscription'   => $inscription->load('arret:id,nom,ordre'),
            'eleve'         => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'circuit'       => ['nom' => $circuit->nom],
            'tarif_mensuel' => $circuit->tarif_mensuel,
        ], "{$eleve->prenom} {$eleve->nom} inscrit sur le circuit '{$circuit->nom}'");
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $inscription = TransportEleve::with(['eleve', 'circuit'])->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);

        return $this->success(null,
            "{$inscription->eleve->prenom} {$inscription->eleve->nom} desinscrit du circuit '{$inscription->circuit->nom}'"
        );
    }

    public function circuitsEleve(string $eleveId): JsonResponse
    {
        $eleve = Eleve::findOrFail($eleveId);
        $inscriptions = TransportEleve::with([
            'circuit:id,nom,vehicule_marque,vehicule_immat,tarif_mensuel',
            'arret:id,nom,ordre,heure_matin,heure_soir',
        ])
            ->where('eleve_id', $eleveId)
            ->where('actif', true)
            ->get();

        return $this->success([
            'eleve'        => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'inscriptions' => $inscriptions,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transport/pointage",
     *     summary="Pointer un élève sur le bus",
     *     tags={"Transport"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"circuit_id","pointages"},
     *             @OA\Property(property="circuit_id", type="string", format="uuid"),
     *             @OA\Property(property="trajet",     type="string", enum={"matin","soir"}),
     *             @OA\Property(property="date",       type="string", format="date"),
     *             @OA\Property(property="pointages",  type="array", @OA\Items(
     *                 @OA\Property(property="eleve_id", type="string", format="uuid"),
     *                 @OA\Property(property="statut",   type="string", enum={"monte","absent","excuse"}),
     *                 @OA\Property(property="arret_id", type="string", format="uuid")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Pointage enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function pointer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circuit_id'            => 'required|uuid|exists:circuits_transport,id',
            'date'                  => 'nullable|date',
            'trajet'                => 'required|in:matin,soir',
            'pointages'             => 'required|array|min:1',
            'pointages.*.eleve_id'  => 'required|uuid|exists:eleves,id',
            'pointages.*.statut'    => 'required|in:monte,absent,excuse',
            'pointages.*.arret_id'  => 'required|uuid|exists:arrets_bus,id',
        ]);

        $date    = $validated['date'] ?? today()->toDateString();
        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $enregistres = 0;
        $absentsNonNotifies = [];

        foreach ($validated['pointages'] as $p) {
            $pointage = PointageBus::updateOrCreate(
                [
                    'tenant_id'  => config('tenant.current_id'),
                    'circuit_id' => $circuit->id,
                    'eleve_id'   => $p['eleve_id'],
                    'date'       => $date,
                    'trajet'     => $validated['trajet'],
                ],
                [
                    'arret_id'     => $p['arret_id'],
                    'statut'       => $p['statut'],
                    'heure_montee' => $p['statut'] === 'monte' ? now()->format('H:i:s') : null,
                    'signale_par'  => 'chauffeur',
                ]
            );

            $enregistres++;

            if ($p['statut'] === 'absent' && !$pointage->sms_parent_envoye) {
                $absentsNonNotifies[] = ['pointage' => $pointage, 'eleve_id' => $p['eleve_id']];
            }
        }

        foreach ($absentsNonNotifies as $item) {
            $this->notifierParentAbsentBus($item['pointage'], $item['eleve_id'], $circuit->nom, $date, $validated['trajet']);
        }

        return $this->success([
            'circuit'     => $circuit->nom,
            'date'        => $date,
            'trajet'      => $validated['trajet'],
            'enregistres' => $enregistres,
            'absents_sms' => count($absentsNonNotifies),
        ], "{$enregistres} pointage(s) enregistre(s)");
    }

    public function pointageDuJour(Request $request, string $circuitId): JsonResponse
    {
        $validated = $request->validate([
            'date'   => 'nullable|date',
            'trajet' => 'nullable|in:matin,soir',
        ]);

        $circuit = CircuitTransport::with('inscriptionsActives.eleve:id,nom,prenom,photo_url')
            ->findOrFail($circuitId);

        $date   = $validated['date'] ?? today()->toDateString();
        $trajet = $validated['trajet'] ?? 'matin';

        $pointages = PointageBus::where('circuit_id', $circuit->id)
            ->where('date', $date)
            ->where('trajet', $trajet)
            ->with('eleve:id,nom,prenom', 'arret:id,nom')
            ->get()
            ->keyBy('eleve_id');

        $liste = $circuit->inscriptionsActives->map(function ($insc) use ($pointages) {
            $p = $pointages->get($insc->eleve_id);
            return [
                'eleve'        => $insc->eleve,
                'arret'        => $insc->arret ?? null,
                'statut'       => $p?->statut ?? 'non_pointe',
                'heure_montee' => $p?->heure_montee,
                'sms_envoye'   => (bool) $p?->sms_parent_envoye,
                'pointage_id'  => $p?->id,
            ];
        });

        return $this->success([
            'circuit' => ['id' => $circuit->id, 'nom' => $circuit->nom],
            'date'    => $date,
            'trajet'  => $trajet,
            'liste'   => $liste,
            'stats'   => [
                'total'      => $liste->count(),
                'montes'     => $liste->where('statut', 'monte')->count(),
                'absents'    => $liste->where('statut', 'absent')->count(),
                'non_pointe' => $liste->where('statut', 'non_pointe')->count(),
            ],
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $today    = today();
        $circuits = CircuitTransport::actifs()->with('arrets')->get();

        $alertesMaintenance = $circuits->flatMap(fn($c) => $c->alertes_maintenance)->filter()->values();

        $pointagesAujourdhui = PointageBus::where('date', $today)
            ->selectRaw("statut, COUNT(*) as total")
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return $this->success([
            'date'                => $today->format('d/m/Y'),
            'nb_circuits'         => $circuits->count(),
            'nb_eleves_total'     => $circuits->sum('nb_eleves_actifs'),
            'alertes_maintenance' => $alertesMaintenance,
            'pointages_aujourd_hui' => [
                'montes'  => (int) ($pointagesAujourdhui['monte']  ?? 0),
                'absents' => (int) ($pointagesAujourdhui['absent'] ?? 0),
                'excuses' => (int) ($pointagesAujourdhui['excuse'] ?? 0),
            ],
            'circuits' => $circuits->map(fn($c) => [
                'id'               => $c->id,
                'nom'              => $c->nom,
                'nb_eleves'        => $c->nb_eleves_actifs,
                'capacite'         => $c->capacite,
                'taux_remplissage' => $c->taux_remplissage,
            ]),
        ], "Tableau de bord transport -- {$today->format('d/m/Y')}");
    }

    private function notifierParentAbsentBus(
        PointageBus $pointage,
        string $eleveId,
        string $nomCircuit,
        string $date,
        string $trajet
    ): void {
        $eleve = Eleve::with('parents')->find($eleveId);
        if (!$eleve) return;

        $trajetLabel = $trajet === 'matin' ? 'matin' : 'soir';
        $dateFormate = \Carbon\Carbon::parse($date)->format('d/m/Y');
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "n'est PAS monte dans le bus {$nomCircuit} ce {$dateFormate} ({$trajetLabel}). "
                 . "Contactez l'etablissement.";

        $smsSent = false;
        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                try {
                    $this->sms->send($parent->telephone_1, $message);
                    $smsSent = true;
                } catch (\Throwable $e) {
                    Log::error('SMS transport absent echoue', ['eleve_id' => $eleveId, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($smsSent) {
            $pointage->update(['sms_parent_envoye' => true, 'sms_envoye_at' => now()]);
        }
    }
}