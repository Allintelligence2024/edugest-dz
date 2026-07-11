<?php

namespace App\Policies;

use App\Models\User;

class FluxInfoPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'enseignant', 'comptable', 'secretariat']);
    }

    public function view(User $user, mixed $flux = null): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'enseignant', 'comptable', 'secretariat']);
    }

    public function gerer(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin']);
    }

    public function exporter(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable']);
    }
}
