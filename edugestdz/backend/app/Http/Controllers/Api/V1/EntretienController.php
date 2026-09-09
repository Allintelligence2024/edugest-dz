<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\EntretienService;
use App\Services\EntretienPreventifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntretienController extends BaseApiController
{
    protected $entretienService;
    protected $preventifService;

    public function __construct(
        EntretienService $entretienService,
        EntretienPreventifService $preventifService
    ) {
        $this->entretienService   = $entretienService;
        $this->preventifService   = $preventifService;
    }

    // ── LOCAUX ──────────────────────────────────────────────────────────────

    public function indexLocaux(Request $request): JsonResponse
    {
        $locaux = LocalBatiment::actifs()
            ->withCount(['interventionsOuvertes as tickets_ouverts'])
            ->orderBy('nom')
            ->get()
            ->map(fn($l) => array_merge($l->toArray(), [
                'type_label'  => $l->type_label,
                'etat_label'  => $l->etat_label,
            ]));

        return $this->success([
            'locaux' => $locaux,
            'stats'  => [
                'total'    => $locaux->count(),
                'critique' => $locaux->where('etat_general', 'critique')->count(),
                'mauvais'  => $locaux->where('etat_general', 'mauvais')->count(),
            ],
        ], 'Locaux récupérés');
    }

    public function storeLocal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:100',
            'type'         => 'required|in:salle_cours,bureau,couloir,cour,sanitaires,cantine,gymnase,entree,parking,laboratoire,bibliotheque,autre',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'etat_general' => 'nullable|in:bon,moyen,mauvais,critique',
            'note'         => 'nullable|string|max:500',
        ]);

        $local = LocalBatiment::create($validated);
        return $this->created(
            array_merge($local->toArray(), ['type_label' => $local->type_label]),
            "Local '{$local->nom}' créé"
        );
    }

    public function updateLocal(Request $request, string $id): JsonResponse
    {
        $local = LocalBatiment::findOrFail($id);
        $validated = $request->validate([
            'nom'          => 'sometimes|string|max:100',
            'etat_general' => 'sometimes|in:bon,moyen,mauvais,critique',
            'etage'        => 'nullable|string|max:20',
            'superficie_m2'=> 'nullable|numeric|min:0',
            'note'         => 'nullable|string|max:500',
        ]);

        $local->update($validated);
        return $this->success(
            array_merge($local->fresh()->toArray(), ['etat_label' => $local->fresh()->etat_label]),
            'Local mis à jour'
        );
    }

    public function destroyLocal(string $id): JsonResponse
    {
        $local = LocalBatiment::findOrFail($id);
        if ($local->interventionsOuvertes()->exists()) {
            return $this->error(
                'Des interventions sont en cours sur ce local',
                'HAS_INTERVENTIONS', 422
            );
        }
        $nom = $local->nom;
        $local->delete();
        return $this->success(null, "Local '{$nom}' supprimé");
    }

    // ── PRESTATAIRES ───────────────────────────────────────────────────────

    public function indexPrestataires(): JsonResponse
    {
        $prestataires = PrestatireEntretien::where('actif', true)
            ->withCount('interventions')
            ->orderBy('nom')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), [
                'specialite_label' => $p->specialite_label,
            ]));

        return $this->success($prestataires, 'Prestataires récupérés');
    }

    public function storePrestataire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:150',
            'specialite'=> 'required|in:plomberie,electricite,peinture,climatisation,menuiserie,maconnerie,nettoyage,informatique,jardinage,securite,general,autre',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'adresse'   => 'nullable|string|max:200',
            'note'      => 'nullable|string|max:500',
        ]);

        $prestataire = PrestatireEntretien::create($validated);
        return $this->created($prestataire, "Prestataire '{$prestataire->nom}' ajouté");
    }

    public function updatePrestataire(Request $request, string $id): JsonResponse
    {
        $prestataire = PrestatireEntretien::findOrFail($id);
        $validated   = $request->validate([
            'nom'       => 'sometimes|string|max:150',
            'telephone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:150',
            'actif'     => 'sometimes|boolean',
            'note'      => 'nullable|string|max:500',
        ]);
        $prestataire->update($validated);
        return $this->success($prestataire->fresh(), 'Prestataire mis à jour');
    }

    // ── INTERVENTIONS ───────────────────────────────────────────────────────

    public function indexInterventions(Request $request): JsonResponse
    {
        $result = $this->entretienService->index($request);
        return $this->paginatedResponse(
            $result['paginator'],
            'Interventions récupérées',
            ['stats' => $result['stats']]
        );
    }

    public function signalerIntervention(Request $request): JsonResponse
    {
        $result = $this->entretienService->signalerIntervention($request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        $r = $result;
        return $this->created([
            'intervention'   => $r['intervention'],
            'priorite_label' => $r['intervention']->priorite_label,
        ], "Intervention signalée : {$r['success']}");
    }

    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $result = $this->entretienService->changerStatut($id, $request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        $r = $result;
        return $this->success(
            $r['intervention'] ?? null,
            "Statut mis à jour : {$r['success']}"
        );
    }

    public function resoudreIntervention(Request $request, string $id): JsonResponse
    {
        $result = $this->entretienService->resoudreIntervention($id, $request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }
        $r = $result;
        return $this->success([
            'intervention' => $r['intervention'],
            'depense_creee' => $r['depense_creee'],
        ], "Intervention résolue — Coût : " . number_format($validated['cout_reel'] ?? 0, 2) . " DA");
    }

    // ── ENTRAÎNEMENTS PRÉVENTIFS ───────────────────────────────────────────

    public function indexPreventif(): JsonResponse
    {
        $result = $this->preventifService->index();
        return $this->success([
            'entretiens' => $result['entretiens'],
            'alertes_30j'=> $result['alertes_30j'],
        ], 'Entretiens préventifs récupérés');
    }

    public function planifierPreventif(Request $request): JsonResponse
    {
        $result = $this->preventifService->planifierPreventif($request->all());
        return $this->created($result['entretien'], $result['success']);
    }

    public function realizierPreventif(Request $request, string $id): JsonResponse
    {
        $result = $this->preventifService->realizierPreventif($id, $request->all());
        if (isset($result['error'])) {
            return $this->error($result['error'], null, 400);
        }
        return $this->success($result['entretien'], $result['success']);
    }

    // ── DASHBOARD ──────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $alertes_30j = $this->preventifService->index()['alertes_30j'];

        $stats = [
            'tickets_ouverts'   => InterventionEntretien::ouverts()->count(),
            'tickets_urgents'   => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'resolus_ce_mois'   => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', today()->month)
                ->whereYear('date_resolution', today()->year)->count(),
            'cout_mois'         => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', today()->month)
                ->whereYear('date_resolution', today()->year)->sum('cout_reel'),
            'locaux_critique'   => LocalBatiment::actifs()->where('etat_general', 'critique')->count(),
            'preventifs_retard' => EntretienPreventif::where('actif', true)
                ->where('prochaine_echeance', '<', today())->count(),
            'preventifs_30j'    => $alertes_30j,
        ];

        $derniersTickets = InterventionEntretien::ouverts()
            ->with(['local:id,nom', 'prestataire:id,nom'])
            ->orderByRaw("CASE priorite WHEN 'urgente' THEN 1 WHEN 'haute' THEN 2 ELSE 3 END")
            ->limit(5)->get();

        return $this->success([
            'stats'          => $stats,
            'derniers_tickets'=> $derniersTickets,
        ], 'Tableau de bord entretien bâtiment');
    }
}