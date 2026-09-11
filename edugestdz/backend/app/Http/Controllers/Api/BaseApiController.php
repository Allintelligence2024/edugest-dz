<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    protected string $model;
    protected array  $with        = [];
    protected array  $searchable  = [];
    protected array  $filterable  = [];
    protected array  $sortable    = ['created_at'];
    protected int    $perPage     = 15;

    /**
     * Colonne portant l'identifiant élève, utilisée pour restreindre
     * automatiquement les listes au périmètre de l'utilisateur (Sprint 2).
     *
     * - 'id'        pour le modèle Eleve lui-même
     * - 'eleve_id'  pour les modèles rattachés (bulletins, notes, factures…)
     * - null        pour désactiver la restriction (référentiels, RH…)
     */
    protected ?string $colonnePerimetreEleve = null;

    protected function indexQuery(Request $request): Builder
    {
        $query = $this->model::query()->with($this->with);

        if ($request->filled('search') && !empty($this->searchable)) {
            $search = $request->search;
            $operator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $operator) {
                foreach ($this->searchable as $field) {
                    $q->orWhere($field, $operator, "%{$search}%");
                }
            });
        }

        foreach ($this->filterable as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->$filter);
            }
        }

        $sort  = $request->sort ?? 'created_at';
        $order = $request->order === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, $this->sortable)) {
            $query->orderBy($sort, $order);
        }

        return $this->appliquerPerimetre($query);
    }

    /**
     * Restreint la requête au périmètre de l'utilisateur courant.
     *
     * Le scope tenant garantit déjà qu'on ne sort pas de l'établissement ;
     * ici on va plus loin : un parent ne voit que SES enfants, un enseignant
     * que les élèves de SES groupes. Neutre pour les rôles administratifs.
     */
    protected function appliquerPerimetre(Builder $query): Builder
    {
        if ($this->colonnePerimetreEleve === null) {
            return $query;
        }

        return app(\App\Services\PerimetreAccesService::class)
            ->restreindreParEleve($query, auth('api')->user(), $this->colonnePerimetreEleve);
    }

    /**
     * Refuse l'accès à un dossier élève hors périmètre.
     * Retourne null si l'accès est permis, sinon une réponse 403.
     */
    protected function verifierPerimetreEleve(?string $eleveId): ?JsonResponse
    {
        $perimetre = app(\App\Services\PerimetreAccesService::class);

        if ($perimetre->peutVoirEleve(auth('api')->user(), $eleveId)) {
            return null;
        }

        return $this->error(
            'Ce dossier élève est hors de votre périmètre',
            'FORBIDDEN',
            403
        );
    }

    protected function success(mixed $data = null, string $message = 'Succès', int $status = 200, array $meta = []): JsonResponse
    {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) $response['data'] = $data;
        if (!empty($meta))  $response['meta'] = $meta;
        return response()->json($response, $status);
    }

    protected function created(mixed $data, string $message = 'Créé avec succès'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message, string $code = 'ERROR', int $status = 400, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => array_filter([
                'code'    => $code,
                'message' => $message,
                'details' => $details ?: null,
            ]),
        ], $status);
    }

    protected function notFound(string $message = 'Ressource introuvable'): JsonResponse
    {
        return $this->error($message, 'NOT_FOUND', 404);
    }

    protected function forbidden(string $message = 'Accès refusé'): JsonResponse
    {
        return $this->error($message, 'FORBIDDEN', 403);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return $this->error('Données invalides', 'VALIDATION_ERROR', 422, $errors);
    }

    protected function paginatedResponse($paginator, string $message = 'Succès', array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => array_merge([
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], $extra),
        ]);
    }
}
