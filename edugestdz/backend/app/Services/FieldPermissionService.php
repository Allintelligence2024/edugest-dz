<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FieldPermissionService
{
    public function peutLire(User $user, string $resource, string $field): bool
    {
        return $this->verifier(
            tenantId: $user->tenant_id,
            roleId: $user->role_id,
            resource: $resource,
            field: $field,
            column: 'can_read'
        );
    }

    public function peutEcrire(User $user, string $resource, string $field): bool
    {
        return $this->verifier(
            tenantId: $user->tenant_id,
            roleId: $user->role_id,
            resource: $resource,
            field: $field,
            column: 'can_write'
        );
    }

    private function verifier(?string $tenantId, ?string $roleId, string $resource, string $field, string $column): bool
    {
        $cacheKey = "fp:{$tenantId}:{$roleId}:{$resource}:{$field}:{$column}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId, $roleId, $resource, $field, $column) {
            try {
                $permission = DB::table('field_permissions')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($roleId) {
                        $q->where('role_id', $roleId)
                          ->orWhereNull('role_id');
                    })
                    ->where('resource', $resource)
                    ->where('field', $field)
                    ->orderByRaw('CASE WHEN role_id = ? THEN 0 ELSE 1 END', [$roleId])
                    ->first();

                if (!$permission) {
                    return false;
                }

                return (bool) $permission->{$column};
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
