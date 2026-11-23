<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;

/**
 * Autorización de socios (RF-07, RF-09, RF-23, RN-11, RN-13).
 *
 * - Administrador: ve todos, crea, edita, elimina lógicamente y restaura.
 * - Recepcionista: ve todos, crea y edita; no elimina ni restaura.
 * - Entrenador: solo lee, y únicamente a los socios que tiene asignados.
 * - Nadie elimina físicamente: un socio con membresías o asistencias
 *   no se borra (RN-11).
 *
 * Los métodos *Any de eliminar y restaurar (deleteAny, restoreAny) no se
 * definen a propósito: Laravel los trata como denegados y las acciones
 * masivas quedan ocultas. Solo se elimina o restaura de a un socio.
 *
 * Esta policy autoriza el acceso a un registro concreto; el acotamiento de
 * las listas lo hace el scope Member::visibleTo().
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist, Role::Trainer);
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->hasRole(Role::Admin, Role::Receptionist)) {
            return true;
        }

        // El entrenador solo ve a sus socios asignados (RN-13, RF-23).
        return $user->hasRole(Role::Trainer) && $member->trainer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    public function update(User $user, Member $member): bool
    {
        return $user->hasRole(Role::Admin, Role::Receptionist);
    }

    /** Eliminación lógica: solo el administrador (RN-11). */
    public function delete(User $user, Member $member): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function restore(User $user, Member $member): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /** Nunca se elimina físicamente (RN-11). */
    public function forceDelete(User $user, Member $member): bool
    {
        return false;
    }
}
