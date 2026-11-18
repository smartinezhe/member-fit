<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Autorización del recurso de usuarios (matriz de la sección 3, RF-06, RF-07).
 *
 * Solo el administrador gestiona usuarios. Nadie los elimina: un usuario con
 * registros solo se desactiva (RN-14), por eso delete/restore/forceDelete son false.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
