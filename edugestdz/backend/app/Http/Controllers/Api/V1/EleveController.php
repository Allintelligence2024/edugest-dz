<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Eleve\StoreEleveRequest;
use App\Http\Requests\Eleve\UpdateEleveRequest;
use App\Models\{Eleve, ParentEleve};
use App\Services\{EleveService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage};

class EleveController extends BaseApiController
{
    protected string $model      = Eleve::class;
    protected array  $with       = ['wilaya', 'parents'];
    protected array  $searchable = ['nom', 'prenom', 'numero_inscription'];
    protected array  $filterable = ['statut', 'niveau_scolaire', 'sexe', 'wilaya_id'];
    protected array  $sortable   = ['nom', 'prenom', 'created_at', 'niveau_scolaire'];
    protected int    $perPage    = 20;

    public function __construct(
        private EleveService $eleveService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $eleves = $this->indexQuery($request)
            ->withCount(['inscriptions' => fn($q) => $q->where('statut', 'validée')])
            ->paginate($request->per_page ?? $this->perPage);

        $stats = cache()->remember("eleves_stats_" . config('tenant.current_id'), 300, fn() => [
            'total'   => Eleve::count(),
            'actifs'  => Eleve::where('statut', 'actif')->count(),
            'nouveaux' => Eleve::whereMonth('created_at', now()->month)->count(),
        ]);

        return $this->paginatedResponse($eleves, 'Élèves récupérés', ['stats' => $stats]);
    }

    public function store(StoreEleveRequest $request): JsonResponse
    {
        $eleve = DB::transaction(function () use ($request) {
            $numero = $this->eleveService->genererNumero();

            $eleve = Eleve::create([
                ...$request->validated(),
                'numero_inscription' => $numero,
                'tenant_id'          => config('tenant.current_id'),
            ]);

            if ($request->has('parents')) {
                foreach ($request->parents as $index => $parentData) {
                    $parent = ParentEleve::firstOrCreate(
                        ['telephone_1' => $parentData['telephone_1'], 'tenant_id' => config('tenant.current_id')],
                        [...$parentData, 'tenant_id' => config('tenant.current_id')]
                    );
                    $eleve->parents()->attach($parent->id, ['est_principal' => $index === 0]);
                }
            }

            $this->eleveService->genererQRCode($eleve);
            return $eleve;
        });

        cache()->forget("eleves_stats_" . config('tenant.current_id'));

        return $this->created(
            $eleve->load(['wilaya', 'commune', 'parents']),
            "Élève {$eleve->nom} {$eleve->prenom} inscrit avec succès"
        );
    }

    public function show(string $id): JsonResponse
    {
        $eleve = Eleve::with([
            'wilaya:id,nom_fr,nom_ar',
            'commune:id,nom_fr',
            'parents',
            'inscriptions' => fn($q) => $q->with('groupe.matiere')->where('statut', 'validée'),
        ])
        ->withCount([
            'presences',
            'presences as presences_presentes' => fn($q) => $q->whereIn('statut', ['présent', 'retard']),
        ])
        ->findOrFail($id);

        return $this->success([
            'eleve'        => $eleve,
            'statistiques' => $this->eleveService->getStatsAcademiques($eleve),
        ]);
    }

    public function update(UpdateEleveRequest $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $eleve->update($request->validated());

        cache()->forget("eleves_stats_" . config('tenant.current_id'));

        return $this->success(
            $eleve->fresh(['wilaya', 'commune', 'parents']),
            'Élève mis à jour avec succès'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        $impayes = $eleve->factures()
            ->whereNotIn('statut', ['payée', 'annulée'])
            ->count();

        if ($impayes > 0) {
            return $this->error(
                "Impossible de supprimer : {$impayes} facture(s) impayée(s)",
                'HAS_UNPAID_INVOICES', 422
            );
        }

        $nom = "{$eleve->nom} {$eleve->prenom}";
        $eleve->update(['statut' => 'inactif']);
        $eleve->delete();

        return $this->success(null, "{$nom} a été archivé");
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        $eleve = Eleve::findOrFail($id);
        if ($eleve->photo_url) {
            Storage::disk('public')->delete($eleve->photo_url);
        }

        $path = $request->file('photo')->store("photos/eleves/{$eleve->tenant_id}", 'public');
        $eleve->update(['photo_url' => $path]);

        return $this->success(['photo_url' => Storage::url($path)], 'Photo mise à jour');
    }

    public function notes(Request $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        $notes = $eleve->notes()
            ->with(['evaluation' => fn($q) => $q->with('groupe.matiere:id,nom_fr,couleur,coefficient')])
            ->when($request->trimestre, fn($q) => $q->whereHas('evaluation', fn($eq) => $eq->where('trimestre', $request->trimestre)))
            ->when($request->groupe_id, fn($q) => $q->whereHas('evaluation', fn($eq) => $eq->where('groupe_id', $request->groupe_id)))
            ->get();

        $parMatiere = $notes->groupBy(fn($n) => $n->evaluation->groupe->matiere->nom_fr)
            ->map(fn($groupNotes, $matiere) => [
                'matiere'     => $matiere,
                'couleur'     => $groupNotes->first()->evaluation->groupe->matiere->couleur ?? '#1E5EBC',
                'coefficient' => $groupNotes->first()->evaluation->groupe->matiere->coefficient,
                'notes'       => $groupNotes->map(fn($n) => [
                    'id'       => $n->id,
                    'note'     => $n->note,
                    'note_sur' => $n->evaluation->note_sur,
                    'appreciation' => $n->appreciation,
                    'type'     => $n->evaluation->type_eval,
                    'date'     => $n->evaluation->date_evaluation,
                    'absent'   => $n->absent,
                ])->values(),
                'moyenne' => $groupNotes->whereNotNull('note')->avg(fn($n) => ($n->note / $n->evaluation->note_sur) * 20),
            ])->values();

        return $this->success([
            'notes'            => $parMatiere,
            'moyenne_generale' => $this->eleveService->calculerMoyenne($eleve->id, $request->groupe_id, $request->trimestre),
            'taux_presence'    => $this->eleveService->calculerTauxPresence($eleve->id),
        ]);
    }

    public function presences(Request $request, string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        $presences = $eleve->presences()
            ->with(['seance' => fn($q) => $q->with([
                'cours.groupe.matiere:id,nom_fr,couleur',
                'cours.enseignant:id,nom,prenom',
            ])])
            ->when($request->mois, fn($q) => $q->whereMonth('created_at', $request->mois))
            ->when($request->annee, fn($q) => $q->whereYear('created_at', $request->annee))
            ->orderByDesc('created_at')
            ->paginate(20);

        $statsPresence = [
            'total'   => $eleve->presences()->count(),
            'presents'=> $eleve->presences()->whereIn('statut', ['présent','retard'])->count(),
            'absents' => $eleve->presences()->where('statut', 'absent')->count(),
            'taux'    => $this->eleveService->calculerTauxPresence($eleve->id),
        ];

        return $this->paginatedResponse($presences, 'Présences récupérées', ['stats' => $statsPresence]);
    }

    public function paiements(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);

        $totalPaye  = $eleve->paiements()
            ->where('statut', 'confirmé')->sum('montant');
        $totalDette = max(0, $eleve->factures()
            ->whereNotIn('statut', ['payée', 'annulée'])->sum('total_ttc')
            - $eleve->paiements()
                ->whereHas('facture', fn($q) => $q->whereNotIn('statut', ['payée', 'annulée']))
                ->where('statut', 'confirmé')
                ->sum('montant'));

        return $this->success([
            'factures'  => $eleve->factures()->with('paiements', 'lignes')->orderByDesc('date_emission')->get(),
            'financier' => [
                'total_paye'  => $totalPaye,
                'total_dette' => $totalDette,
                'nb_factures' => $eleve->factures()->count(),
                'nb_impayes'  => $eleve->factures()->whereNotIn('statut', ['payée', 'annulée'])->count(),
            ],
        ]);
    }

    public function bulletins(string $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        return $this->success(
            $eleve->bulletins()->with('groupe')->orderByDesc('created_at')->get()
        );
    }

    public function statistiques(string $id): JsonResponse
    {
        $eleve = Eleve::withCount([
            'inscriptions',
            'presences as total_presences' => fn($q) => $q->where('statut', 'present'),
        ])->findOrFail($id);

        return $this->success($eleve);
    }

    public function inscrire(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'groupe_id'      => 'required|uuid|exists:groupes,id',
            'annee_scolaire' => 'nullable|string|regex:/^\d{4}-\d{4}$/',
            'date_inscription'=> 'nullable|date',
        ]);

        $eleve  = Eleve::findOrFail($id);
        $groupe = \App\Models\Groupe::findOrFail($validated['groupe_id']);

        $dejaInscrit = $eleve->inscriptions()
            ->where('groupe_id', $groupe->id)
            ->where('statut', 'validée')
            ->exists();

        if ($dejaInscrit) {
            return $this->error("L'élève est déjà inscrit dans ce groupe", 'ALREADY_ENROLLED', 409);
        }

        $nbInscrits = $groupe->inscriptions()->where('statut', 'validée')->count();
        if ($nbInscrits >= $groupe->capacite_max) {
            return $this->error("Le groupe est complet ({$groupe->capacite_max} max)", 'GROUP_FULL', 409);
        }

        $inscription = $eleve->inscriptions()->create([
            'tenant_id'       => config('tenant.current_id'),
            'groupe_id'       => $groupe->id,
            'date_inscription'=> $validated['date_inscription'] ?? now()->toDateString(),
            'statut'          => 'validée',
            'inscrit_par'     => auth('api')->id(),
        ]);

        return $this->created($inscription->load('groupe'), "Inscription au groupe {$groupe->nom} validée");
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        // Stub — à implémenter avec maatwebsite/excel
        $request->file('fichier')->store('imports');
        return $this->success(null, 'Import en cours de traitement');
    }

    public function export(Request $request)
    {
        $eleves = Eleve::with(['wilaya', 'parents'])->get();
        return $this->success($eleves);
    }
}
