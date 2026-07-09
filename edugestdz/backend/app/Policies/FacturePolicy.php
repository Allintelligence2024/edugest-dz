<?php

namespace App\Policies;

use App\Models\User;

class FacturePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable']);
    }

    public function exporter(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable']);
    }
}
