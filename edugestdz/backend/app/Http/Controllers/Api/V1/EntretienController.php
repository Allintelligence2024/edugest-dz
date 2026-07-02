<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Depense;
use App\Models\EntretienPreventif;
use App\Models\InterventionEntretien;
use App\Models\LocalBatiment;
use App\Models\PrestatireEntretien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntretienController extends BaseApiController
{
    // ═══════════════════════════════════════════
    // LOCAUX
    // ═══════════════════════════════════════════

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
        $local     = LocalBatiment::findOrFail($id);
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

    // ═══════════════════════════════════════════
    // PRESTATAIRES
    // ═══════════════════════════════════════════

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

    // ═══════════════════════════════════════════
    // INTERVENTIONS (TICKETS)
    // ═══════════════════════════════════════════

    /**
     * @OA\Get(
     *     path="/api/v1/entretien/interventions",
     *     summary="Liste des interventions d'entretien",
     *     tags={"Entretien"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"signale","en_cours","en_attente","resolu","annule"})),
     *     @OA\Parameter(name="priorite", in="query", @OA\Schema(type="string", enum={"urgente","haute","normale","basse"})),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Interventions paginées", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function indexInterventions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut'   => 'nullable|in:signale,en_cours,en_attente,resolu,annule',
            'priorite' => 'nullable|in:urgente,haute,normale,basse',
            'type'     => 'nullable|string',
            'local_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = InterventionEntretien::with([
            'local:id,nom,type',
            'prestataire:id,nom,specialite',
        ])
            ->when($validated['statut']   ?? null, fn($q, $s) => $q->where('statut', $s))
            ->when($validated['priorite'] ?? null, fn($q, $p) => $q->where('priorite', $p))
            ->when($validated['type']     ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($validated['local_id'] ?? null, fn($q, $l) => $q->where('local_id', $l))
            ->orderByRaw("CASE priorite
                WHEN 'urgente' THEN 1
                WHEN 'haute'   THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse'   THEN 4
                ELSE 5 END")
            ->orderByDesc('date_signalement')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_ouverts' => InterventionEntretien::ouverts()->count(),
            'urgentes'      => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'hautes'        => InterventionEntretien::ouverts()->priorite('haute')->count(),
            'resolues_mois' => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', now()->month)->count(),
        ];

        return $this->paginatedResponse($paginator, 'Interventions récupérées', ['stats' => $stats]);
    }

    public function showIntervention(string $id): JsonResponse
    {
        $intervention = InterventionEntretien::with([
            'local:id,nom,type,etage',
            'prestataire:id,nom,specialite,telephone',
            'signalePar:id,nom,prenom',
            'depense',
        ])->findOrFail($id);

        return $this->success([
            'intervention'   => $intervention,
            'priorite_label' => $intervention->priorite_label,
            'statut_label'   => $intervention->statut_label,
            'duree_jours'    => $intervention->duree_jours,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/entretien/interventions",
     *     summary="Signaler une intervention d'entretien",
     *     tags={"Entretien"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"titre","type","priorite"},
     *             @OA\Property(property="titre",         type="string"),
     *             @OA\Property(property="description",   type="string", nullable=true),
     *             @OA\Property(property="type",          type="string", enum={"panne","degradation","entretien_preventif","renovation","nettoyage","inspection"}),
     *             @OA\Property(property="priorite",      type="string", enum={"urgente","haute","normale","basse"}),
     *             @OA\Property(property="local_id",      type="string", format="uuid", nullable=true),
     *             @OA\Property(property="prestataire_id",type="string", format="uuid", nullable=true),
     *             @OA\Property(property="cout_estime",   type="number", format="float", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Intervention créée", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function signalerIntervention(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:panne,degradation,entretien_preventif,renovation,nettoyage,inspection',
            'priorite'        => 'required|in:urgente,haute,normale,basse',
            'local_id'        => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'  => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_signalement'=> 'nullable|date',
            'cout_estime'     => 'nullable|numeric|min:0',
        ]);

        $validated['date_signalement'] = $validated['date_signalement'] ?? today()->toDateString();
        $validated['signale_par']      = auth()->id();
        $validated['statut']           = 'signale';

        $intervention = InterventionEntretien::create($validated);

        return $this->created([
            'intervention'   => $intervention->load(['local', 'prestataire']),
            'priorite_label' => $intervention->priorite_label,
        ], "Intervention signalée : {$intervention->titre}");
    }

    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut'                  => 'required|in:en_cours,en_attente,annule',
            'prestataire_id'          => 'nullable|uuid|exists:prestataires_entretien,id',
            'date_debut_intervention' => 'nullable|date',
        ]);

        $intervention = InterventionEntretien::findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return $this->error('Cette intervention est déjà résolue', 'DEJA_RESOLU', 409);
        }

        $data = ['statut' => $validated['statut']];

        if ($validated['statut'] === 'en_cours') {
            $data['date_debut_intervention'] = $validated['date_debut_intervention'] ?? today()->toDateString();
            if (isset($validated['prestataire_id'])) {
                $data['prestataire_id'] = $validated['prestataire_id'];
            }
        }

        $intervention->update($data);
        return $this->success(
            $intervention->fresh(['local', 'prestataire']),
            "Statut mis à jour : {$intervention->statut_label}"
        );
    }

    public function resoudreIntervention(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'cout_reel'            => 'required|numeric|min:0',
            'rapport_intervention' => 'nullable|string|max:2000',
            'date_resolution'      => 'nullable|date',
            'date_entretien_suivant'=> 'nullable|date|after:today',
            'etat_local_apres'     => 'nullable|in:bon,moyen,mauvais,critique',
        ]);

        $intervention = InterventionEntretien::with('local')->findOrFail($id);

        if ($intervention->statut === 'resolu') {
            return $this->error('Déjà résolu', 'DEJA_RESOLU', 409);
        }

        DB::transaction(function () use ($intervention, $validated) {
            $intervention->update([
                'statut'                => 'resolu',
                'cout_reel'             => $validated['cout_reel'],
                'rapport_intervention'  => $validated['rapport_intervention'] ?? null,
                'date_resolution'       => $validated['date_resolution'] ?? today()->toDateString(),
                'date_entretien_suivant'=> $validated['date_entretien_suivant'] ?? null,
            ]);

            if (isset($validated['etat_local_apres']) && $intervention->local) {
                $intervention->local->update(['etat_general' => $validated['etat_local_apres']]);
            }

            if ($validated['cout_reel'] > 0) {
                $depense = Depense::create([
                    'tenant_id'    => config('tenant.current_id'),
                    'categorie'    => 'maintenance_reparation',
                    'libelle'      => "Entretien : {$intervention->titre}",
                    'montant'      => $validated['cout_reel'],
                    'date_depense' => today()->toDateString(),
                    'mois'         => now()->month,
                    'annee'        => now()->year,
                    'fournisseur'  => $intervention->prestataire?->nom,
                    'mode_paiement'=> 'cash',
                    'statut'       => 'validee',
                    'saisie_par'   => auth()->id(),
                    'note'         => "Lié à l'intervention #{$intervention->id}",
                ]);

                $intervention->update(['depense_id' => $depense->id]);
            }
        });

        return $this->success([
            'intervention' => $intervention->fresh(['local', 'prestataire', 'depense']),
            'depense_creee'=> $validated['cout_reel'] > 0,
        ], "Intervention résolue — Coût : " . number_format($validated['cout_reel'], 2) . " DA");
    }

    // ═══════════════════════════════════════════
    // ENTRETIENS PRÉVENTIFS
    // ═══════════════════════════════════════════

    public function indexPreventif(): JsonResponse
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

        return $this->success([
            'entretiens' => $entretiens,
            'alertes_30j'=> $alertes,
        ], 'Entretiens préventifs récupérés');
    }

    public function planifierPreventif(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'                 => 'required|string|max:150',
            'description'         => 'nullable|string|max:500',
            'local_id'            => 'nullable|uuid|exists:locaux_batiment,id',
            'prestataire_id'      => 'nullable|uuid|exists:prestataires_entretien,id',
            'frequence'           => 'required|in:hebdomadaire,mensuel,trimestriel,semestriel,annuel,biennal',
            'prochaine_echeance'  => 'required|date',
            'cout_estime'         => 'nullable|numeric|min:0',
        ]);

        $entretien = EntretienPreventif::create($validated);
        return $this->created($entretien->load(['local', 'prestataire']), "Entretien planifié : {$entretien->nom}");
    }

    public function realiserPreventif(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'cout_reel'   => 'nullable|numeric|min:0',
            'observations'=> 'nullable|string|max:500',
        ]);

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

        if (($validated['cout_reel'] ?? 0) > 0) {
            Depense::create([
                'tenant_id'    => config('tenant.current_id'),
                'categorie'    => 'maintenance_reparation',
                'libelle'      => "Entretien préventif : {$entretien->nom}",
                'montant'      => $validated['cout_reel'],
                'date_depense' => today()->toDateString(),
                'mois'         => now()->month,
                'annee'        => now()->year,
                'fournisseur'  => $entretien->prestataire?->nom,
                'mode_paiement'=> 'cash',
                'statut'       => 'validee',
                'saisie_par'   => auth()->id(),
            ]);
        }

        return $this->success([
            'entretien'        => $entretien->fresh(),
            'prochaine_echeance'=> $prochaine->format('d/m/Y'),
        ], "Entretien réalisé · Prochain : {$prochaine->format('d/m/Y')}");
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today = today();

        $stats = [
            'tickets_ouverts'   => InterventionEntretien::ouverts()->count(),
            'tickets_urgents'   => InterventionEntretien::ouverts()->priorite('urgente')->count(),
            'resolus_ce_mois'   => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->count(),
            'cout_mois'         => InterventionEntretien::where('statut', 'resolu')
                ->whereMonth('date_resolution', $today->month)
                ->whereYear('date_resolution', $today->year)
                ->sum('cout_reel'),
            'locaux_critique'   => LocalBatiment::actifs()->where('etat_general', 'critique')->count(),
            'preventifs_retard' => EntretienPreventif::where('actif', true)
                ->where('prochaine_echeance', '<', $today)->count(),
            'preventifs_30j'    => EntretienPreventif::where('actif', true)
                ->whereBetween('prochaine_echeance', [$today, $today->copy()->addDays(30)])->count(),
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
