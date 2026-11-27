<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula la membresía más reciente de un socio (RF-14, RN-08).
 *
 * Reglas que aplica:
 * - Solo se anula la membresía más reciente NO anulada del socio, es decir, la
 *   de mayor vencimiento. Anulada esa, la anterior pasa a ser la más reciente
 *   y puede anularse a su vez (misma definición que el estado del socio, RN-05).
 * - El motivo es obligatorio: entre MIN_REASON_LENGTH y MAX_REASON_LENGTH
 *   caracteres, sin contar espacios en los extremos.
 * - La membresía anulada deja de contar como vigente (RN-01, RN-05) y su pago
 *   se excluye de los ingresos (RN-09): eso lo resuelven esas consultas, no
 *   esta Action. El pago y las asistencias ya registradas se conservan.
 * - Se bloquea la fila del socio, igual que en RenewMembership, para que anular
 *   y renovar a la vez se ejecuten una detrás de la otra (RNF-03).
 *
 * Esta Action NO autoriza: que solo el administrador pueda anular lo decide
 * MembershipPolicy::cancel() en la capa de interfaz.
 */
class CancelMembership
{
    /** Longitud mínima del motivo (RN-08). */
    public const MIN_REASON_LENGTH = 5;

    /** Longitud máxima del motivo: el tamaño de la columna cancellation_reason. */
    public const MAX_REASON_LENGTH = 255;

    /**
     * @throws ValidationException Si el motivo es inválido (reason) o la membresía no se puede anular (membership).
     */
    public function handle(Membership $membership, string $reason): Membership
    {
        $reason = trim($reason);
        $length = mb_strlen($reason);

        // RN-08: validación barata antes de abrir la transacción.
        if ($length < self::MIN_REASON_LENGTH || $length > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => 'El motivo debe tener entre '.self::MIN_REASON_LENGTH.' y '.self::MAX_REASON_LENGTH.' caracteres.',
            ]);
        }

        return DB::transaction(function () use ($membership, $reason): Membership {
            // Bloqueo del socio (RNF-03). Incluye eliminados lógicamente: anular
            // corrige un registro existente y no debe quedar bloqueado por eso.
            Member::query()->withTrashed()->lockForUpdate()->findOrFail($membership->member_id);

            // Estado actual: se relee dentro de la transacción por si otra
            // petición la anuló mientras tanto.
            $current = Membership::query()->findOrFail($membership->getKey());

            if ($current->cancelled_at !== null) {
                throw ValidationException::withMessages([
                    'membership' => 'La membresía ya está anulada.',
                ]);
            }

            // RN-08: solo la más reciente no anulada (mayor vencimiento; el id desempata).
            if (! $current->isLatestNotCancelled()) {
                throw ValidationException::withMessages([
                    'membership' => 'Solo se puede anular la membresía más reciente del socio.',
                ]);
            }

            // No son asignables en masa a propósito: solo esta Action anula.
            $current->cancelled_at = now();
            $current->cancellation_reason = $reason;
            $current->save();

            return $current;
        });
    }
}
