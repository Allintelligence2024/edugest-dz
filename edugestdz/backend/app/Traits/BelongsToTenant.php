<?php
// backend/app/Traits/BelongsToTenant.php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait BelongsToTenant
{
    // ── Boot automatique du scope ──
    protected static function bootBelongsToTenant(): void
    {
        // Filtre automatique par tenant sur toutes les requêtes
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = config('tenant.current_id');

            if ($tenantId === null) {
                // Fail-closed : aucun contexte tenant → 0 résultat.
                //
                // Ne JAMAIS transformer ce cas en « pas de filtre » : ce serait
                // exposer les données de tous les établissements d'un coup.
                $query->whereRaw('1 = 0');

                // Une requête sans tenant est presque toujours un bug de câblage
                // (job de queue, commande artisan, middleware oublié). Le silence
                // la ferait passer pour un simple « aucun résultat ».
                if (config('tenant.strict', true) && !app()->runningUnitTests()) {
                    Log::warning('Requête sans contexte tenant — résultat forcé à vide', [
                        'model' => $query->getModel()::class,
                        'table' => $query->getModel()->getTable(),
                    ]);
                }

                return;
            }

            $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
        });

        // Injection automatique du tenant_id à la création
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $resolved = config('tenant.current_id')
                    ?? Auth::user()?->tenant_id;

                if ($resolved === null) {
                    throw new \RuntimeException(
                        'Impossible de créer sans tenant résolu. '
                        . 'Assurez-vous que config(tenant.current_id) ou Auth::user()->tenant_id est défini.'
                    );
                }

                $model->tenant_id = $resolved;
            }
        });
    }

    // ── Désactiver le scope (Super-Admin uniquement) ──
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    // ── Relation vers le tenant ──
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    // ── Scope manuel (si besoin) ──
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
