<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\HasCrudOperations;
use App\Models\{Groupe, Inscription, Eleve};
use Illuminate\Http\{Request, JsonResponse};

class GroupeController extends BaseApiController
{
    use HasCrudOperations;

    protected string $model      = Groupe::class;
    protected array  $with       = ['matiere'];
    protected array  $searchable = ['nom', 'code'];
    protected array  $filterable = ['statut', 'niveau_scolaire', 'type_groupe', 'matiere_id'];
    protected array  $sortable   = ['nom', 'niveau_scolaire', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->indexQuery($request)
            ->withCount(['inscriptions as nb_eleves' => fn($q) => $q->where('statut', 'validée')])
            ->paginate($request->per_page ?? $this->perPage);

        return $this->paginatedResponse($paginator, 'Groupes récupérés', [
            'stats' => [
                'total'    => Groupe::count(),
                'actifs'   => Groupe::where('statut', 'actif')->count(),
                'complets' => Groupe::where('statut', 'complet')->count(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'required|string|max:100',
            'matiere_id'      => 'required|uuid|exists:matieres,id',
            'niveau_scolaire' => 'required|string',
            'capacite_max'    => 'required|integer|min:1|max:50',
            'type_groupe'     => 'nullable|in:individuel,groupe,intensif,en_ligne',
            'description'     => 'nullable|string',
        ]);

        $data['type_groupe'] ??= 'groupe';
        $groupe = Groupe::create($data);

        return $this->created(
            $groupe->load('matiere'),
            "Groupe {$groupe->nom} créé"
        );
    }

    public function show(string $id): JsonResponse
    {
        $groupe = Groupe::with([
            'matiere',
            'cours.enseignant',
            'cours.salle',
            'inscriptions.eleve.wilaya',
        ])->findOrFail($id);

        return $this->success([
            'groupe' => $groupe,
            'stats'  => [
                'nb_eleves'       => $groupe->inscriptions->where('statut', 'validée')->count(),
                'places_restantes' => $groupe->capacite_max - $groupe->inscriptions->where('statut', 'validée')->count(),
                'nb_cours'        => $groupe->cours->where('statut', 'actif')->count(),
                'taux_remplissage'=> $groupe->capacite_max > 0
                    ? round(($groupe->inscriptions->where('statut', 'validée')->count() / $groupe->capacite_max) * 100)
                    : 0,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $groupe = Groupe::findOrFail($id);
        $data   = $request->validate([
            'nom'          => 'sometimes|string|max:100',
            'capacite_max' => 'sometimes|integer|min:1',
            'type_groupe'  => 'sometimes|in:individuel,groupe,intensif,en_ligne',
            'statut'       => 'sometimes|in:actif,complet,fermé',
            'description'  => 'nullable|string',
        ]);

        $groupe = $this->performUpdate($groupe, $data);
        return $this->success($groupe->fresh('matiere'), 'Groupe mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $groupe   = Groupe::findOrFail($id);
        $nbEleves = $groupe->inscriptions()->where('statut', 'validée')->count();

        if ($nbEleves > 0) {
            return $this->error("Ce groupe a {$nbEleves} élève(s) inscrit(s)", 'GROUP_HAS_STUDENTS', 422);
        }

        $this->performDelete($groupe);
        return $this->success(null, "Groupe {$groupe->nom} supprimé");
    }

    public function eleves(string $id): JsonResponse
    {
        $groupe = Groupe::findOrFail($id);
        $eleves = Eleve::whereHas('inscriptions', fn($q) =>
            $q->where('groupe_id', $id)->where('statut', 'validée')
        )->with('wilaya')->get();

        return $this->success($eleves, "Élèves du groupe {$groupe->nom}");
    }

    public function addEleve(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'eleve_id'  => 'required|uuid|exists:eleves,id',
            'date_debut' => 'nullable|date',
        ]);

        $groupe = Groupe::findOrFail($id);

        $dejaInscrit = Inscription::where('groupe_id', $id)
            ->where('eleve_id', $request->eleve_id)
            ->where('statut', 'validée')
            ->exists();

        if ($dejaInscrit) {
            return $this->error('Cet élève est déjà inscrit dans ce groupe', 'ALREADY_ENROLLED', 409);
        }

        $nbActuels = $groupe->inscriptions()->where('statut', 'validée')->count();
        if ($nbActuels >= $groupe->capacite_max) {
            return $this->error('Le groupe est complet', 'GROUP_FULL', 409);
        }

        $inscription = Inscription::create([
            'tenant_id'       => config('tenant.current_id'),
            'eleve_id'        => $request->eleve_id,
            'groupe_id'       => $id,
            'date_inscription'=> today(),
            'date_debut'      => $request->date_debut ?? today(),
            'statut'          => 'validée',
            'inscrit_par'     => auth('api')->id(),
        ]);

        if ($nbActuels + 1 >= $groupe->capacite_max) {
            $groupe->update(['statut' => 'complet']);
        }

        return $this->created(
            $inscription->load('eleve'),
            'Élève ajouté au groupe avec succès'
        );
    }

    public function removeEleve(string $id, string $eleveId): JsonResponse
    {
        $inscription = Inscription::where('groupe_id', $id)
            ->where('eleve_id', $eleveId)
            ->where('statut', 'validée')
            ->firstOrFail();

        $inscription->update([
            'statut'  => 'annulée',
            'date_fin' => today(),
        ]);

        $groupe = Groupe::find($id);
        if ($groupe && $groupe->statut === 'complet') {
            $groupe->update(['statut' => 'actif']);
        }

        return $this->success(null, 'Élève retiré du groupe');
    }
}
