<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Membresía de un socio: plan contratado y vigencia inclusiva (RN-01, RN-02).
 *
 * Las fechas las calcula la Action RenewMembership (Día 4), nunca el usuario.
 * La anulación (RN-08) la hace la Action CancelMembership, que asigna
 * cancelled_at y cancellation_reason directamente: por eso esos campos no son
 * asignables en masa.
 */
#[Fillable(['member_id', 'plan_id', 'created_by', 'starts_on', 'ends_on'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Membresías no anuladas: las únicas que cuentan para vigencia,
     * estado e ingresos (RN-01, RN-05, RN-08, RN-09).
     *
     * @param  Builder<Membership>  $query
     */
    #[Scope]
    protected function notCancelled(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Usuario que registró la membresía.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pago único de la membresía (RN-04).
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
