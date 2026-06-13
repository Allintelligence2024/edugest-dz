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
            if ($tenantId = config('tenant.current_id')) {
                $query->where((new static)->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Injection automatique du tenant_id à la création
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = config('tenant.current_id')
                    ?? Auth::user()?->tenant_id;
            }
        });
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
