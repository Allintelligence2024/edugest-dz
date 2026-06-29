<?php
// backend/app/Traits/BelongsToTenant.php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    // ── Boot automatique du scope ──
    protected static function bootBelongsToTenant(): void
    {
        // Filtre automatique par tenant sur toutes les requêtes
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = config('tenant.current_id');

            if ($tenantId === null) {
                // Fail-safe : aucun contexte tenant → 0 résultats, pas d'exposition
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where((new static)->getTable() . '.tenant_id', $tenantId);
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
