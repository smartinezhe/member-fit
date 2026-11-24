<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la membresía de un socio y su pago en una sola transacción (RF-13).
 *
 * Reglas que aplica:
 * - RN-02: starts_on es el mayor entre hoy y el día siguiente al vencimiento
 *   de la última membresía NO anulada del socio, de modo que las renovaciones
 *   anticipadas son continuas. ends_on = starts_on + duración del plan - 1 día
 *   (fechas inclusivas). Las fechas nunca las elige el usuario.
 * - RN-03: el valor es, por defecto, el precio vigente del plan; si se indica,
 *   debe ser mayor que 0.
 * - RN-04: se crea un único pago por el valor total.
 * - RN-12: no se puede usar un plan inactivo.
 * - RNF-03: la fila del socio se bloquea (lockForUpdate) dentro de la
 *   transacción para que dos renovaciones simultáneas no produzcan fechas
 *   superpuestas. MySQL no tiene restricciones de exclusión de rangos, por lo
 *   que esta continuidad se garantiza aquí, en la aplicación.
 *
 * Esta Action NO autoriza: quién puede registrar membresías (administrador y
 * recepcionista) lo decide la capa de interfaz con su Policy.
 */
class RenewMembership
{
    /**
     * @param  int|float|string|null  $amount  Valor pagado en COP; null usa el precio vigente del plan.
     *
     * @throws ValidationException Si el valor es inválido (amount) o el plan está inactivo (plan_id).
     */
    public function handle(
        Member $member,
        Plan $plan,
        User $creator,
        PaymentMethod $method,
        int|float|string|null $amount = null,
    ): Membership {
        // RN-03: validación barata antes de abrir la transacción.
        if ($amount !== null && (! is_numeric($amount) || (float) $amount <= 0)) {
            throw ValidationException::withMessages([
                'amount' => 'El valor debe ser mayor que 0.',
            ]);
        }

        return DB::transaction(function () use ($member, $plan, $creator, $method, $amount): Membership {
            // Bloqueo del socio (RNF-03). Excluye a los eliminados lógicamente:
            // si el socio fue eliminado, lanza ModelNotFoundException.
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());

            // Plan vigente: se relee dentro de la transacción para usar su
            // estado y su precio actuales (RN-03, RN-12).
            $currentPlan = Plan::query()->findOrFail($plan->getKey());

            if (! $currentPlan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => 'El plan seleccionado está inactivo.',
                ]);
            }

            $startsOn = $this->resolveStartDate($lockedMember);
            $endsOn = $startsOn->copy()->addDays($currentPlan->duration_days - 1);

            $membership = Membership::query()->create([
                'member_id' => $lockedMember->getKey(),
                'plan_id' => $currentPlan->getKey(),
                'created_by' => $creator->getKey(),
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
            ]);

            // RN-04: un único pago por el valor total, con la fecha y hora del registro.
            $membership->payment()->create([
                'amount' => $amount ?? $currentPlan->price,
                'method' => $method,
                'paid_at' => now(),
            ]);

            return $membership->load('payment');
        });
    }

    /**
     * Fecha de inicio (RN-02): el mayor entre hoy y el día siguiente al
     * vencimiento de la última membresía no anulada del socio.
     */
    private function resolveStartDate(Member $member): Carbon
    {
        $today = today();

        $lastEndsOn = $member->memberships()->notCancelled()->max('ends_on');

        if ($lastEndsOn === null) {
            return $today;
        }

        $dayAfter = Carbon::parse($lastEndsOn)->addDay()->startOfDay();

        return $dayAfter->greaterThan($today) ? $dayAfter : $today;
    }
}
