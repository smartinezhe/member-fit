<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;

/**
 * Autorización de membresías (RF-07, RF-13, RF-23, RN-02).
 *
 * - Administrador y recepcionista: ver el historial y registrar membresías.
 * - Entrenador: sin acceso al historial, porque incluye valores y métodos de
 *   pago, que no puede consultar (RF-23).
 * - Nadie edita ni elimina: las fechas las calcula el sistema (RN-02) y una
 *   membresía errónea se anula, no se modifica ni se borra (RN-08).
 *
 * La anulación (RN-08, solo administrador) se añade como habilidad propia
 * (cancel) en el Día 5.
 */
class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function update(User $user, Membership $membership): bool
    {
        return false;
    }

    public function delete(User $user, Membership $membership): bool
    {
        return false;
    }

    public function restore(User $user, Membership $membership): bool
    {
        return false;
    }

    public function forceDelete(User $user, Membership $membership): bool
    {
        return false;
    }
}
