<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\User;

/**
 * Autorización de planes de membresía (RF-07, RF-08, RN-12).
 *
 * - Administrador: ver, crear y editar (incluye activar/desactivar).
 * - Recepcionista: solo ver, para elegir plan al registrar membresías.
 * - Entrenador: sin acceso.
 * - Nadie elimina planes: se desactivan (RN-12).
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return false;
    }

    public function restore(User $user, Plan $plan): bool
    {
        return false;
    }

    public function forceDelete(User $user, Plan $plan): bool
    {
        return false;
    }
}
