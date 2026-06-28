<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\HasCrudOperations;
use App\Models\{Enseignant, User};
use App\Http\Requests\Enseignant\StoreEnseignantRequest;
use App\Http\Requests\Enseignant\UpdateEnseignantRequest;
use App\Services\{PaieService, PlanningService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Hash};

class EnseignantController extends BaseApiController
{
    use HasCrudOperations;

    protected string $model      = Enseignant::class;
    protected array  $with       = ['user', 'matieres', 'wilaya'];
    protected array  $searchable = ['nom', 'prenom', 'telephone', 'email', 'matricule'];
    protected array  $filterable = ['statut', 'type_contrat', 'wilaya_id'];
    protected array  $sortable   = ['nom', 'prenom', 'date_embauche', 'created_at'];

    public function __construct(
        private PaieService     $paieService,
        private PlanningService $planningService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->indexQuery($request)
            ->paginate($request->per_page ?? $this->perPage);

        $stats = [
            'total'      => Enseignant::count(),
            'actifs'     => Enseignant::where('statut', 'actif')->count(),
            'vacataires' => Enseignant::where('type_contrat', 'vacataire')->count(),
            'cdi'        => Enseignant::where('type_contrat', 'CDI')->count(),
        ];

        return $this->paginatedResponse($paginator, 'Enseignants récupérés', ['stats' => $stats]);
    }

    public function store(StoreEnseignantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $enseignant = DB::transaction(function () use ($data) {
            $user = User::create([
                'tenant_id' => config('tenant.current_id'),
                'nom'       => $data['nom'],
                'prenom'    => $data['prenom'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password'] ?? 'Ens@' . now()->year),
                'role_id'   => \App\Models\Role::where('nom', 'enseignant')->value('id'),
                'statut'    => 'actif',
            ]);

            $data['matricule'] = $this->genererMatricule();
            $data['user_id']   = $user->id;

            $matieres = $data['matieres'] ?? [];
            unset($data['matieres'], $data['password']);

            $enseignant = Enseignant::create($data);

            if (!empty($matieres)) {
                foreach ($matieres as $m) {
                    $enseignant->matieres()->attach($m['matiere_id'], [
                        'niveau_scolaire' => $m['niveau_scolaire'],
                        'est_principal'   => $m['est_principal'] ?? true,
                    ]);
                }
            }

            return $enseignant;
        });

        return $this->created(
            $enseignant->load($this->with),
            "Enseignant {$enseignant->nom} {$enseignant->prenom} créé"
        );
    }

    public function show(string $id): JsonResponse
    {
        $enseignant = Enseignant::with([
            ...$this->with,
            'contrats',
            'cours.groupe.matiere',
        ])->findOrFail($id);

        return $this->success([
            'enseignant'   => $enseignant,
            'statistiques' => $this->getStats($enseignant),
        ]);
    }

    public function update(UpdateEnseignantRequest $request, string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);
        $data       = $request->validated();
        $matieres   = $data['matieres'] ?? null;
        unset($data['matieres']);

        $enseignant = $this->performUpdate($enseignant, $data,
            $matieres ? ['matieres' => $this->formatMatieresPivot($matieres)] : []
        );

        if ($request->hasFile('photo')) {
            $path = $this->uploadFile($request->file('photo'), 'photos/enseignants');
            $enseignant->update(['photo_url' => $path]);
        }

        return $this->success(
            $enseignant->fresh($this->with),
            'Enseignant mis à jour'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);

        $coursActifs = $enseignant->cours()->where('statut', 'actif')->count();
        if ($coursActifs > 0) {
            return $this->error(
                "Impossible de supprimer : {$coursActifs} cours actif(s)",
                'HAS_ACTIVE_COURSES', 422
            );
        }

        $nom = "{$enseignant->nom} {$enseignant->prenom}";
        $this->performDelete($enseignant);

        return $this->success(null, "{$nom} supprimé avec succès");
    }

    public function planning(Request $request, string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);

        $debut = $request->date_debut ?? now()->startOfWeek()->toDateString();
        $fin   = $request->date_fin   ?? now()->endOfWeek()->toDateString();

        $planning = $this->planningService->getPlanningHebdomadaire($debut, $fin, ['enseignant_id' => $id]);

        return $this->success([
            'planning'   => $planning,
            'enseignant' => ['id' => $enseignant->id, 'nom' => "{$enseignant->nom} {$enseignant->prenom}"],
            'periode'    => ['debut' => $debut, 'fin' => $fin],
        ]);
    }

    public function setDisponibilites(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'disponibilites'                => 'required|array',
            'disponibilites.*.jour_semaine' => 'required|integer|between:0,6',
            'disponibilites.*.heure_debut'  => 'required|date_format:H:i',
            'disponibilites.*.heure_fin'    => 'required|date_format:H:i|after:disponibilites.*.heure_debut',
            'disponibilites.*.disponible'   => 'boolean',
        ]);

        $enseignant = Enseignant::findOrFail($id);

        $enseignant->update(['disponibilites' => $request->disponibilites]);

        return $this->success(
            $enseignant->fresh(),
            'Disponibilités mises à jour'
        );
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:2048']);
        $enseignant = Enseignant::findOrFail($id);
        $path = $request->file('photo')->store('photos/enseignants', 'public');
        $enseignant->update(['photo_url' => $path]);
        return $this->success(['photo_url' => $path], 'Photo uploadée');
    }

    public function statistiques(string $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);
        return $this->success($this->getStats($enseignant));
    }

    private function getStats(Enseignant $enseignant): array
    {
        $moisActuel = now()->month;
        $annee      = now()->year;

        return [
            'nb_groupes'   => $enseignant->cours()->where('statut', 'actif')->distinct('groupe_id')->count(),
            'cours_actifs' => $enseignant->cours()->where('statut', 'actif')->count(),
            'paie_mois'    => $enseignant->paies()
                                         ->where('mois', $moisActuel)
                                         ->where('annee', $annee)
                                         ->value('salaire_net') ?? 0,
        ];
    }

    private function genererMatricule(): string
    {
        $annee = now()->year;
        $last  = Enseignant::withoutGlobalScope('tenant')
            ->where('matricule', 'LIKE', "ENS-{$annee}-%")
            ->orderByDesc('matricule')->value('matricule');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;
        return sprintf("ENS-%d-%03d", $annee, $seq);
    }

    private function formatMatieresPivot(array $matieres): array
    {
        $formatted = [];
        foreach ($matieres as $m) {
            $formatted[$m['matiere_id']] = [
                'niveau_scolaire' => $m['niveau_scolaire'],
                'est_principal'   => $m['est_principal'] ?? true,
            ];
        }
        return $formatted;
    }
}
