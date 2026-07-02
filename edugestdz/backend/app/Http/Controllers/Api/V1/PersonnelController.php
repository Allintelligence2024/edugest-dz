<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CongePersonnel;
use App\Models\PersonnelNonEnseignant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/personnel",
     *     summary="Liste du personnel non-enseignant",
     *     tags={"Personnel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"actif","inactif","suspendu"})),
     *     @OA\Parameter(name="poste",    in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Personnel paginé", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'poste'    => 'nullable|string',
            'statut'   => 'nullable|in:actif,inactif,suspendu',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = PersonnelNonEnseignant::query();

        if (!empty($validated['search'])) {
            $query->search($validated['search']);
        }
        if (!empty($validated['poste'])) {
            $query->poste($validated['poste']);
        }
        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderBy('nom')->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total'          => PersonnelNonEnseignant::count(),
            'actifs'         => PersonnelNonEnseignant::actifs()->count(),
            'par_poste'      => PersonnelNonEnseignant::actifs()
                ->selectRaw('poste, COUNT(*) as total')
                ->groupBy('poste')
                ->pluck('total', 'poste'),
        ];

        return $this->paginatedResponse($paginator, 'Personnel récupéré', ['stats' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'poste'            => 'required|in:femme_menage,surveillant,chauffeur,proviseur,directeur_adjoint,secretaire,technicien,agent_securite,autre',
            'poste_libelle'    => 'nullable|string|max:100',
            'type_contrat'     => 'nullable|in:CDI,CDD,vacataire,stagiaire',
            'date_embauche'    => 'required|date',
            'salaire_base'     => 'required|numeric|min:0',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'date_naissance'   => 'nullable|date',
            'date_fin_contrat' => 'nullable|date|after:date_embauche',
            'num_ss'           => 'nullable|string|max:30',
            'num_cnas'         => 'nullable|string|max:30',
        ]);

        $validated['matricule'] = $this->genererMatricule($validated['poste']);

        $agent = PersonnelNonEnseignant::create($validated);

        return $this->created(
            $agent,
            "Agent {$agent->nom_complet} créé ({$agent->poste_affiche})"
        );
    }

    public function show(string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::with([
            'pointages' => fn($q) => $q->orderByDesc('date')->limit(30),
            'conges'    => fn($q) => $q->orderByDesc('date_debut')->limit(10),
        ])->findOrFail($id);

        return $this->success([
            'agent'             => $agent,
            'poste_affiche'     => $agent->poste_affiche,
            'anciennete_ans'    => $agent->anciennete_ans,
            'solde_conges'      => $agent->soldeCongesRestants(),
            'present_aujourdhui'=> $agent->isPresent(),
            'stats_mois'        => $this->statsMois($agent),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);

        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'poste'            => 'sometimes|in:femme_menage,surveillant,chauffeur,proviseur,directeur_adjoint,secretaire,technicien,agent_securite,autre',
            'poste_libelle'    => 'nullable|string|max:100',
            'type_contrat'     => 'sometimes|in:CDI,CDD,vacataire,stagiaire',
            'date_embauche'    => 'sometimes|date',
            'salaire_base'     => 'sometimes|numeric|min:0',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'statut'           => 'sometimes|in:actif,inactif,suspendu',
            'date_fin_contrat' => 'nullable|date',
            'num_ss'           => 'nullable|string|max:30',
            'num_cnas'         => 'nullable|string|max:30',
        ]);

        $agent->update($validated);

        return $this->success($agent->fresh(), 'Agent mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);
        $nom   = $agent->nom_complet;
        $agent->delete();

        return $this->success(null, "{$nom} supprimé");
    }

    public function conges(string $id): JsonResponse
    {
        $agent  = PersonnelNonEnseignant::findOrFail($id);
        $conges = CongePersonnel::where('agent_id', $agent->id)
            ->orderByDesc('date_debut')
            ->get();

        return $this->success([
            'agent'          => ['id' => $agent->id, 'nom' => $agent->nom_complet],
            'conges'         => $conges,
            'solde_restant'  => $agent->soldeCongesRestants(),
            'droit_annuel'   => 30,
        ]);
    }

    public function demanderConge(Request $request, string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);

        $validated = $request->validate([
            'date_debut' => 'required|date|after_or_equal:today',
            'date_fin'   => 'required|date|after_or_equal:date_debut',
            'type'       => 'required|in:conge_annuel,maladie,maternite,sans_solde,autre',
            'motif'      => 'nullable|string|max:500',
        ]);

        $nbJours = \Carbon\Carbon::parse($validated['date_debut'])
            ->diffInWeekdays(\Carbon\Carbon::parse($validated['date_fin'])) + 1;

        if ($validated['type'] === 'conge_annuel') {
            $solde = $agent->soldeCongesRestants();
            if ($nbJours > $solde) {
                return $this->error(
                    "Solde insuffisant : {$solde} jour(s) disponible(s), {$nbJours} demandé(s)",
                    'SOLDE_INSUFFISANT',
                    422
                );
            }
        }

        $conge = CongePersonnel::create([
            'tenant_id'  => config('tenant.current_id'),
            'agent_id'   => $agent->id,
            'date_debut' => $validated['date_debut'],
            'date_fin'   => $validated['date_fin'],
            'nb_jours'   => $nbJours,
            'type'       => $validated['type'],
            'motif'      => $validated['motif'] ?? null,
            'statut'     => 'en_attente',
        ]);

        return $this->created([
            'conge'  => $conge,
            'agent'  => ['nom' => $agent->nom_complet],
            'nb_jours' => $nbJours,
        ], "Demande de congé soumise ({$nbJours} jour(s))");
    }

    public function statuerConge(Request $request, string $congeId): JsonResponse
    {
        $validated = $request->validate([
            'statut' => 'required|in:approuve,refuse',
            'motif'  => 'nullable|string|max:300',
        ]);

        $conge = CongePersonnel::with('agent')->findOrFail($congeId);

        if ($conge->statut !== 'en_attente') {
            return $this->error(
                'Ce congé a déjà été traité',
                'DEJA_TRAITE',
                409
            );
        }

        $conge->update([
            'statut'      => $validated['statut'],
            'approuve_par'=> auth()->id(),
            'approuve_at' => now(),
        ]);

        $action = $validated['statut'] === 'approuve' ? 'approuvé' : 'refusé';

        return $this->success([
            'conge' => $conge->fresh(),
            'agent' => ['nom' => $conge->agent->nom_complet],
        ], "Congé {$action}");
    }

    public function tableauBord(): JsonResponse
    {
        $today  = today();
        $agents = PersonnelNonEnseignant::actifs()
            ->with(['pointageAujourdhui'])
            ->get();

        $data = $agents->map(function (PersonnelNonEnseignant $a) {
            $p = $a->pointageAujourdhui;
            return [
                'agent'         => [
                    'id'     => $a->id,
                    'nom'    => $a->nom_complet,
                    'poste'  => $a->poste_affiche,
                    'photo'  => $a->photo_url,
                ],
                'statut'        => $p?->statut ?? 'absent',
                'heure_arrivee' => $p?->heure_arrivee ? substr($p->heure_arrivee, 0, 5) : null,
                'heure_depart'  => $p?->heure_depart  ? substr($p->heure_depart, 0, 5)  : null,
                'pointe'        => (bool) $p?->heure_arrivee,
            ];
        });

        $parPoste = $data->groupBy(fn($a) => $a['agent']['poste'])->map->values();

        return $this->success([
            'date'     => $today->format('d/m/Y'),
            'par_poste'=> $parPoste,
            'stats'    => [
                'total'    => $agents->count(),
                'presents' => $data->where('statut', 'present')->count(),
                'retards'  => $data->where('statut', 'retard')->count(),
                'absents'  => $data->where('pointe', false)->count(),
            ],
        ], "Tableau de bord personnel — {$today->format('d/m/Y')}");
    }

    private function genererMatricule(string $poste): string
    {
        $prefixes = [
            'femme_menage'     => 'FM',
            'surveillant'      => 'SU',
            'chauffeur'        => 'CH',
            'proviseur'        => 'PR',
            'directeur_adjoint'=> 'DA',
            'secretaire'       => 'SE',
            'technicien'       => 'TC',
            'agent_securite'   => 'AS',
            'autre'            => 'AG',
        ];

        $prefix = $prefixes[$poste] ?? 'AG';
        $annee  = now()->year;

        $last = PersonnelNonEnseignant::withoutGlobalScope('tenant')
            ->where('matricule', 'LIKE', "{$prefix}-{$annee}-%")
            ->orderByDesc('matricule')
            ->value('matricule');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return sprintf('%s-%d-%03d', $prefix, $annee, $seq);
    }

    private function statsMois(PersonnelNonEnseignant $agent): array
    {
        $debut = now()->startOfMonth()->toDateString();
        $fin   = today()->toDateString();

        $pointages = $agent->pointages()
            ->whereBetween('date', [$debut, $fin])
            ->get();

        return [
            'jours_travailles' => $pointages->whereNotNull('heure_arrivee')->count(),
            'absences'         => $pointages->where('statut', 'absent')->count(),
            'retards'          => $pointages->where('statut', 'retard')->count(),
        ];
    }
}
