<?php

namespace App\Observers;

use App\Models\Enseignant;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\ParentEleve;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Invalide le cache de périmètre (PerimetreAccesService) quand la structure
 * d'accès change.
 *
 * Sans cela, une inscription d'élève ou un changement de filiation resterait
 * invisible jusqu'à 5 minutes — soit un parent qui ne voit pas encore son
 * enfant, soit (plus grave) un parent qui continue de voir un élève auquel
 * il n'est plus rattaché.
 */
class PerimetreCacheObserver
{
    public function saved($model): void
    {
        $this->purger($model);
    }

    public function deleted($model): void
    {
        $this->purger($model);
    }

    private function purger($model): void
    {
        match (true) {
            $model instanceof ParentEleve => $this->purgerUtilisateur($model->user_id),
            $model instanceof Enseignant  => $this->purgerUtilisateur($model->user_id),

            // Une inscription ou un groupe modifie le périmètre de tous les
            // enseignants du tenant : on purge par balayage ciblé.
            $model instanceof Inscription,
            $model instanceof Groupe      => $this->purgerTenant($model->tenant_id),

            default => null,
        };
    }

    private function purgerUtilisateur(?string $userId): void
    {
        if (!$userId) {
            return;
        }

        Cache::forget("perimetre.parent.{$userId}.eleves");
        Cache::forget("perimetre.enseignant.{$userId}.eleves");
        Cache::forget("perimetre.enseignant.{$userId}.groupes");
    }

    private function purgerTenant(?string $tenantId): void
    {
        if (!$tenantId) {
            return;
        }

        // Seuls parents et enseignants ont un périmètre restreint : inutile
        // de balayer les comptes administratifs.
        User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereHas('role', fn ($q) => $q->whereIn('nom', ['parent', 'enseignant']))
            ->pluck('id')
            ->each(fn ($id) => $this->purgerUtilisateur($id));
    }
}
