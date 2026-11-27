<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una membresía (RN-03, RN-04).
 *
 * Cada membresía tiene un solo pago, por el valor total, con método fijo
 * (Efectivo, Transferencia o Tarjeta). El valor se guarda en el pago, de modo
 * que los cambios posteriores del precio del plan no lo afectan (RN-03).
 * Lo crea la Action RenewMembership junto con la membresía, en una sola
 * transacción; un pago nunca se edita ni se elimina: si la membresía se
 * anula, el pago deja de contar en los ingresos (RN-08, RN-09).
 */
#[Fillable(['membership_id', 'amount', 'method', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Pagos que cuentan como ingreso: los de membresías no anuladas (RN-09).
     *
     * Es la única definición de «ingreso» del sistema: la usan el listado de
     * pagos, los indicadores y los reportes. El pago de una membresía anulada
     * se conserva, pero deja de sumarse (RN-08).
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function countedAsIncome(Builder $query): void
    {
        $query->whereHas('membership', fn (Builder $membership) => $membership->notCancelled());
    }

    /**
     * Pagos registrados en un rango de fechas, con extremos inclusivos (RN-09).
     *
     * Los ingresos se agrupan por la fecha de paid_at en America/Bogota. Se
     * compara contra el rango completo de los días (de las 00:00:00 del primero
     * a las 23:59:59 del último) y no con whereDate, para que MySQL use el
     * índice de paid_at. Un extremo nulo deja el rango abierto de ese lado.
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function paidBetween(Builder $query, ?CarbonInterface $from, ?CarbonInterface $until): void
    {
        if ($from !== null) {
            $query->where('paid_at', '>=', $from->copy()->startOfDay());
        }

        if ($until !== null) {
            $query->where('paid_at', '<=', $until->copy()->endOfDay());
        }
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
