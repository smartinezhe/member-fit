<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;

/**
 * Autorización de pagos (RF-15, RN-04).
 *
 * - Administrador: consulta el listado de pagos y los ingresos.
 * - Recepcionista y entrenador: sin acceso al listado (matriz de la sección 3).
 *   El valor y el método de un pago se ven en el historial del socio solo para
 *   administrador y recepcionista, a través de MembershipPolicy.
 * - Nadie crea, edita ni elimina pagos desde la interfaz: nacen únicamente en
 *   la Action RenewMembership, junto con su membresía, y son inmutables (RN-04).
 *   Un error se corrige anulando la membresía (RN-08), no tocando el pago.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
