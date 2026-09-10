<?php

namespace App\Policies;

use App\Models\User;

class ElevePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable', 'secretariat']);
    }

    public function view(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable', 'secretariat', 'enseignant']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'secretariat']);
    }

    public function update(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'secretariat']);
    }

    public function delete(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin']);
    }

    public function exporter(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'super_admin', 'comptable']);
    }
}
