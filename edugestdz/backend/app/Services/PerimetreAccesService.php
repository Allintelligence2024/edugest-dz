<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\ParentEleve;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 2 (suite) — Périmètre d'accès intra-tenant.
 *
 * Le RBAC par route répond à « ce RÔLE peut-il appeler cet endpoint ? ».
 * Ce service répond à la question suivante, plus fine :
 *
 *   « CET utilisateur peut-il voir CETTE ligne ? »
 *
 * Deux restrictions concrètes :
 *   - un parent ne doit voir que SES enfants (lien eleve_parent) ;
 *   - un enseignant ne doit voir que les élèves de SES groupes, pas tout
 *     l'établissement.
 *
 * Les autres rôles (admin, gestionnaire, secretariat, comptable) ont une
 * vision complète de leur tenant : pour eux les méthodes sont neutres.
 */
class PerimetreAccesService
{
    /** Rôles ayant une vision complète du tenant. */
    private const ROLES_VISION_GLOBALE = ['super_admin', 'admin', 'gestionnaire', 'secretariat', 'comptable'];

    public function aVisionGlobale(?User $user): bool
    {
        return $user !== null && in_array($user->nomRole(), self::ROLES_VISION_GLOBALE, true);
    }

    /**
     * Identifiants des élèves visibles par l'utilisateur.
     *
     * @return list<string>|null  null = aucune restriction (vision globale)
     */
    public function elevesAutorises(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        if ($this->aVisionGlobale($user)) {
            return null;
        }

        return match ($user->nomRole()) {
            'parent'     => $this->elevesDuParent($user),
            'enseignant' => $this->elevesDeLEnseignant($user),
            default      => [],
        };
    }

    /** Élèves rattachés au parent via la table pivot eleve_parent. */
    public function elevesDuParent(User $user): array
    {
        return Cache::remember(
            "perimetre.parent.{$user->id}.eleves",
            now()->addMinutes(5),
            function () use ($user) {
                $parentIds = ParentEleve::withoutGlobalScope('tenant')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $user->tenant_id)
                    ->pluck('id');

                if ($parentIds->isEmpty()) {
                    return [];
                }

                return DB::table('eleve_parent')
                    ->whereIn('parent_id', $parentIds)
                    ->pluck('eleve_id')
                    ->unique()
                    ->values()
                    ->all();
            }
        );
    }

    /** Élèves inscrits dans un groupe dont l'utilisateur est l'enseignant. */
    public function elevesDeLEnseignant(User $user): array
    {
        return Cache::remember(
            "perimetre.enseignant.{$user->id}.eleves",
            now()->addMinutes(5),
            function () use ($user) {
                $enseignantId = Enseignant::withoutGlobalScope('tenant')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $user->tenant_id)
                    ->value('id');

                if (!$enseignantId) {
                    return [];
                }

                $groupeIds = $this->groupesDeLEnseignant($user);

                if ($groupeIds === []) {
                    return [];
                }

                return DB::table('inscriptions')
                    ->whereIn('groupe_id', $groupeIds)
                    ->pluck('eleve_id')
                    ->unique()
                    ->values()
                    ->all();
            }
        );
    }

    /**
     * Groupes pilotés par l'enseignant : ceux dont il est titulaire
     * (groupes.enseignant_id) et ceux où il assure un cours.
     *
     * @return list<string>
     */
    public function groupesDeLEnseignant(User $user): array
    {
        return Cache::remember(
            "perimetre.enseignant.{$user->id}.groupes",
            now()->addMinutes(5),
            function () use ($user) {
                $enseignantId = Enseignant::withoutGlobalScope('tenant')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $user->tenant_id)
                    ->value('id');

                if (!$enseignantId) {
                    return [];
                }

                $titulaire = DB::table('groupes')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('enseignant_id', $enseignantId)
                    ->pluck('id');

                $viaCours = DB::table('cours')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('enseignant_id', $enseignantId)
                    ->whereNotNull('groupe_id')
                    ->pluck('groupe_id');

                return $titulaire->merge($viaCours)->unique()->values()->all();
            }
        );
    }

    /**
     * Applique la restriction de périmètre à une requête Eloquent.
     *
     * @param  Builder  $query
     * @param  string   $colonne  colonne portant l'identifiant élève
     */
    public function restreindreParEleve(Builder $query, ?User $user, string $colonne = 'eleve_id'): Builder
    {
        $autorises = $this->elevesAutorises($user);

        if ($autorises === null) {
            return $query;
        }

        // Liste vide => aucun résultat, jamais « tous les résultats ».
        return $query->whereIn($colonne, $autorises ?: ['00000000-0000-0000-0000-000000000000']);
    }

    /** L'utilisateur a-t-il le droit de consulter ce dossier élève ? */
    public function peutVoirEleve(?User $user, ?string $eleveId): bool
    {
        if ($user === null || $eleveId === null) {
            return false;
        }

        $autorises = $this->elevesAutorises($user);

        return $autorises === null || in_array($eleveId, $autorises, true);
    }

    /** Purge le cache de périmètre (à appeler si la filiation change). */
    public function oublier(User $user): void
    {
        Cache::forget("perimetre.parent.{$user->id}.eleves");
        Cache::forget("perimetre.enseignant.{$user->id}.eleves");
        Cache::forget("perimetre.enseignant.{$user->id}.groupes");
    }
}
