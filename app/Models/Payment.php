<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
