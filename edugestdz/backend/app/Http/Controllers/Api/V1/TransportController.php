<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\PointageBus;
use App\Services\Sms\SmsService;
use App\Services\TransportCircuitService;
use App\Services\TransportEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransportController extends BaseApiController
{
    public function __construct(
        private readonly TransportCircuitService $circuits,
        private readonly TransportEnrollmentService $inscriptions,
        private readonly SmsService $sms,
    ) {}

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
        $result = $this->circuits->index($request);

        return $this->success([
            'circuits' => $result['circuits'],
            'stats'    => $result['stats'],
        ], 'Circuits recuperes');
    }

    public function storeCircuit(Request $request): JsonResponse
    {
        $result = $this->circuits->storeCircuit($request->all());

        return $this->created($result['circuit'], $result['message']);
    }

    public function showCircuit(string $id): JsonResponse
    {
        return $this->success($this->circuits->showCircuit($id));
    }

    public function updateCircuit(Request $request, string $id): JsonResponse
    {
        $result = $this->circuits->updateCircuit($id, $request->all());

        return $this->success($result['circuit'], 'Circuit mis a jour');
    }

    public function destroyCircuit(string $id): JsonResponse
    {
        $result = $this->circuits->destroyCircuit($id);

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success(null, $result['success']);
    }

    public function indexArrets(string $circuitId): JsonResponse
    {
        $result = $this->circuits->indexArrets($circuitId);

        return $this->success($result['arrets'], "Arrets du circuit '{$result['circuit_nom']}'");
    }

    public function storeArret(Request $request, string $circuitId): JsonResponse
    {
        $result = $this->circuits->storeArret($circuitId, $request->all());

        return $this->created($result['arret'], $result['message']);
    }

    public function updateArret(Request $request, string $id): JsonResponse
    {
        $result = $this->circuits->updateArret($id, $request->all());

        return $this->success($result['arret'], 'Arret mis a jour');
    }

    public function destroyArret(string $id): JsonResponse
    {
        $result = $this->circuits->destroyArret($id);

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success(null, $result['success']);
    }

    public function inscrireEleve(Request $request): JsonResponse
    {
        $result = $this->inscriptions->inscriptionEleve($request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->created([
            'inscription'   => $result['inscription'],
            'eleve'         => $result['eleve'],
            'circuit'       => $result['circuit'],
            'tarif_mensuel' => $result['tarif_mensuel'],
        ], $result['message']);
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $result = $this->inscriptions->desinscription($id);

        return $this->success(null, $result['message']);
    }

    public function circuitsEleve(string $eleveId): JsonResponse
    {
        return $this->success($this->inscriptions->circuitsEleve($eleveId));
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
